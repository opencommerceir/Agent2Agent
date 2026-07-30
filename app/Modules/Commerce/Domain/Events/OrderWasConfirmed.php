<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Order;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched right after OrderWasPlaced in this phase — there is no
 * payment/fraud-check gate yet, so placing an Order confirms it
 * immediately (Order::confirm()'s own docblock).
 */
final class OrderWasConfirmed
{
    public function __construct(
        public readonly Order $order,
    ) {
    }
}
