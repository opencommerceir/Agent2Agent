<?php

namespace App\Modules\Notifications\Domain\Events;

use App\Modules\Notifications\Domain\Entities\Notification;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched by SendNotificationAction once all retry attempts are
 * exhausted — a channel failure is business-normal (a bad email address,
 * a down webhook endpoint), never an exception the MCP caller sees
 * (rule §7 of this stage's own request).
 */
final class NotificationFailed
{
    public function __construct(
        public readonly Notification $notification,
    ) {
    }
}
