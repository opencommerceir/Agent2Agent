<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Application\DTOs\NotificationPreferenceData;
use App\Modules\Notifications\Domain\Entities\NotificationPreference;
use App\Modules\Notifications\Domain\Repositories\NotificationPreferenceRepositoryInterface;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\RecipientType;

/**
 * Upserts by the 4-part key (tenantId, recipientType, recipientId,
 * notificationType, channelType) — "set" always reflects the latest
 * call, never requiring a separate create-then-update flow.
 */
final class SetUserPreferenceAction
{
    public function __construct(
        private readonly NotificationPreferenceRepositoryInterface $preferences,
    ) {
    }

    public function execute(
        int $tenantId,
        string $recipientType,
        int $recipientId,
        string $notificationType,
        string $channelType,
        bool $isEnabled,
    ): NotificationPreferenceData {
        $recipientTypeVo = RecipientType::from($recipientType);
        $notificationTypeVo = NotificationType::from($notificationType);
        $channelTypeVo = ChannelType::from($channelType);

        $preference = $this->preferences->find($tenantId, $recipientTypeVo, $recipientId, $notificationTypeVo, $channelTypeVo);

        if ($preference) {
            $preference->setEnabled($isEnabled);
        } else {
            $preference = NotificationPreference::create(
                $tenantId, $recipientTypeVo, $recipientId, $notificationTypeVo, $channelTypeVo, $isEnabled,
            );
        }

        $preference = $this->preferences->save($preference);

        return NotificationPreferenceData::fromEntity($preference);
    }
}
