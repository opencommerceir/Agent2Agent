<?php

namespace App\Modules\Notifications\Domain\ValueObjects;

/**
 * `TicketCreated` is modeled but has no registered Listener this stage —
 * only the Listeners explicitly requested by their own stage
 * (`ShipmentStatusChanged`/`OrderPlaced`/`PointsEarned`, and — Phase 5
 * Stage 5, §7.25 — `SubscriptionPaymentFailed`) are wired, the same
 * "enum case exists before its own Listener does" shape
 * `EventType::CartAbandoned` had before the Tech Debt Sprint wired it.
 */
enum NotificationType: string
{
    case OrderPlaced = 'order_placed';
    case ShipmentStatusChanged = 'shipment_status_changed';
    case PointsEarned = 'points_earned';
    case TicketCreated = 'ticket_created';
    case SubscriptionPaymentFailed = 'subscription_payment_failed';
}
