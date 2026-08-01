<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Application\DTOs\NotificationData;
use App\Modules\Notifications\Application\Services\ChannelSenderRegistry;
use App\Modules\Notifications\Domain\Entities\Notification;
use App\Modules\Notifications\Domain\Events\NotificationFailed;
use App\Modules\Notifications\Domain\Events\NotificationWasSent;
use App\Modules\Notifications\Domain\Exceptions\ChannelSendFailedException;
use App\Modules\Notifications\Domain\Repositories\NotificationChannelRepositoryInterface;
use App\Modules\Notifications\Domain\Repositories\NotificationPreferenceRepositoryInterface;
use App\Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;
use App\Modules\Notifications\Domain\Services\NotificationDispatcher;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use App\Modules\Notifications\Domain\ValueObjects\RecipientType;
use Illuminate\Support\Facades\Event;

/**
 * The module's central Action — every Listener and the direct
 * `notification.message.send` MCP capability both funnel through this
 * one call.
 *
 * Preference checking is conditional on knowing *whose* preference to
 * check: $recipientType/$recipientId are nullable. A Listener (which
 * knows the real Customer/Agent id) passes both, and
 * NotificationDispatcher::shouldSend() gates the send on them; the direct
 * MCP capability (a raw recipient string, no id) omits both, so nothing
 * is checked — there is structurally no Preference row to look up for a
 * caller-supplied string with no owning id.
 *
 * Retry: up to 3 attempts, exponential backoff (50ms/100ms/200ms) between
 * them, only on a ChannelSendFailedException. Marks the Notification
 * Sent + dispatches NotificationWasSent on the first attempt that
 * succeeds; marks it Failed + dispatches NotificationFailed (never
 * throws — rule §7 of this stage's own request) only once every attempt
 * is exhausted.
 *
 * Stays synchronous — matches this codebase's QUEUE_CONNECTION=sync
 * default and the fact that no Job class exists anywhere yet. Queueing
 * this later needs only a Job wrapping this same call, not a structural
 * change.
 */
final class SendNotificationAction
{
    private const MAX_ATTEMPTS = 3;

    private const BASE_BACKOFF_MICROSECONDS = 50_000; // 50ms

    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly NotificationChannelRepositoryInterface $channels,
        private readonly NotificationPreferenceRepositoryInterface $preferences,
        private readonly NotificationDispatcher $dispatcher,
        private readonly ChannelSenderRegistry $senders,
    ) {
    }

    public function execute(
        int $tenantId,
        NotificationType $type,
        ChannelType $channelType,
        Recipient $recipient,
        string $subject,
        string $body,
        ?int $templateId = null,
        array $metadata = [],
        ?RecipientType $recipientType = null,
        ?int $recipientId = null,
    ): ?NotificationData {
        $channel = $this->channels->findByType($tenantId, $channelType);

        $preference = ($recipientType !== null && $recipientId !== null)
            ? $this->preferences->find($tenantId, $recipientType, $recipientId, $type, $channelType)
            : null;

        if (! $this->dispatcher->shouldSend($preference, $channel?->isActive() ?? false)) {
            return null;
        }

        $notification = Notification::create($tenantId, $type, $channelType, $recipient, $subject, $body, $templateId, $metadata);
        $notification = $this->notifications->save($notification);

        $sender = $this->senders->get($channelType);
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $sender->send($recipient->value(), $subject, $body);

                $notification->markSent();
                $notification = $this->notifications->save($notification);
                Event::dispatch(new NotificationWasSent($notification));

                return NotificationData::fromEntity($notification);
            } catch (ChannelSendFailedException $e) {
                $lastError = $e;

                if ($attempt < self::MAX_ATTEMPTS) {
                    usleep(self::BASE_BACKOFF_MICROSECONDS * (2 ** ($attempt - 1)));
                }
            }
        }

        $notification->markFailed($lastError->getMessage());
        $notification = $this->notifications->save($notification);
        Event::dispatch(new NotificationFailed($notification));

        return NotificationData::fromEntity($notification);
    }
}
