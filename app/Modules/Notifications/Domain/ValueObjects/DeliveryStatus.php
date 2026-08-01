<?php

namespace App\Modules\Notifications\Domain\ValueObjects;

/**
 * `Delivered` is modeled (rule §d/`NotificationWasDelivered`) but nothing
 * transitions a Notification into it this stage — no real
 * delivery-confirmation channel (an email open-tracking pixel, an SMS
 * carrier webhook) exists to drive it, the same "needs live credentials
 * to test honestly" reasoning every Connector in this codebase already
 * carries. `Sent` is as far as any Notification travels today.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
