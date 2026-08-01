<?php

namespace App\Modules\Notifications\Domain\Events;

use App\Modules\Notifications\Domain\Entities\Notification;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched by SendNotificationAction after a ChannelSenderInterface
 * call succeeds (on the first attempt, or after a retry recovers).
 */
final class NotificationWasSent
{
    public function __construct(
        public readonly Notification $notification,
    ) {
    }
}
