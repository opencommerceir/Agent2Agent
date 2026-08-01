<?php

namespace App\Modules\Notifications\Domain\Events;

use App\Modules\Notifications\Domain\Entities\Notification;

/**
 * Modeled per this stage's own request, but nothing dispatches this event
 * yet — no real delivery-confirmation mechanism (an email open-tracking
 * pixel, an SMS carrier webhook) exists to drive it, the same "needs live
 * credentials to test honestly" reasoning every Connector in this
 * codebase already carries (see DeliveryStatus::Delivered's own
 * docblock). Documented scaffolding, not a silently dropped requirement.
 */
final class NotificationWasDelivered
{
    public function __construct(
        public readonly Notification $notification,
    ) {
    }
}
