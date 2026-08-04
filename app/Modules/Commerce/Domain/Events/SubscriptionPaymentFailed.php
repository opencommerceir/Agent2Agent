<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Subscription;
use App\Modules\Commerce\Domain\Entities\SubscriptionInvoice;

/**
 * Dispatched on every failed charge attempt (the first failure and every
 * subsequent retry, not just the 3rd/final one) — carries both the
 * Subscription and the specific SubscriptionInvoice that failed, the same
 * "already-fetched entity, no re-fetch needed" shape `OrderWasPlaced`/
 * `ShipmentStatusChanged` already establish. Notifications' own
 * `SubscriptionPaymentFailedListener` (App\Modules\Notifications) is the
 * one real Listener reacting to this — see that class's own docblock.
 */
final class SubscriptionPaymentFailed
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly SubscriptionInvoice $invoice,
    ) {
    }
}
