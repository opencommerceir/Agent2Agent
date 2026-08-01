<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Application\DTOs\NotificationData;
use App\Modules\Notifications\Domain\Exceptions\NotificationNotFoundException;
use App\Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;

final class GetNotificationAction
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {
    }

    public function execute(int $notificationId, int $tenantId): NotificationData
    {
        $notification = $this->notifications->findById($notificationId, $tenantId);

        if (! $notification) {
            throw new NotificationNotFoundException("Notification [{$notificationId}] does not exist.");
        }

        return NotificationData::fromEntity($notification);
    }
}
