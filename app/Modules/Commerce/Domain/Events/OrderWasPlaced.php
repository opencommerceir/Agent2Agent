<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Order;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a new Order has been persisted with status Pending.
 */
final class OrderWasPlaced
{
    public function __construct(
        public readonly Order $order,
    ) {
    }
}
