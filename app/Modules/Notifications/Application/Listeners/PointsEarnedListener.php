<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Loyalty\Domain\Events\PointsWereEarned;
use App\Modules\Notifications\Application\Actions\SendNotificationAction;
use App\Modules\Notifications\Domain\Repositories\NotificationTemplateRepositoryInterface;
use App\Modules\Notifications\Domain\Services\TemplateRenderer;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use App\Modules\Notifications\Domain\ValueObjects\RecipientType;

/**
 * Reacts to Loyalty's own PointsWereEarned (carries the full
 * LoyaltyAccount + the points amount — no further Repository lookup
 * needed). Always an in_app notification (no email/phone concept for
 * this one, per this stage's own example) — the Recipient value is the
 * Customer's own id (InAppSender never reads it; the persisted
 * Notification row itself is what an in-app UI polls).
 */
final class PointsEarnedListener
{
    public function __construct(
        private readonly NotificationTemplateRepositoryInterface $templates,
        private readonly TemplateRenderer $renderer,
        private readonly SendNotificationAction $sendNotification,
    ) {
    }

    public function handle(PointsWereEarned $event): void
    {
        $account = $event->account;

        $template = $this->templates->findActive($account->tenantId(), NotificationType::PointsEarned, ChannelType::InApp);

        if (! $template) {
            return;
        }

        $variables = [
            'points' => (string) $event->points,
            'new_balance' => (string) $account->currentBalance()->value(),
        ];

        $this->sendNotification->execute(
            tenantId: $account->tenantId(),
            type: NotificationType::PointsEarned,
            channelType: ChannelType::InApp,
            recipient: new Recipient((string) $account->customerId()),
            subject: $this->renderer->render($template->subjectTemplate(), $variables),
            body: $this->renderer->render($template->bodyTemplate(), $variables),
            templateId: $template->id(),
            recipientType: RecipientType::Customer,
            recipientId: $account->customerId(),
        );
    }
}
