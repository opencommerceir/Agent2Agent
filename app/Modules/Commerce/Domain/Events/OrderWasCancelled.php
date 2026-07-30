<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Order;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after an Order has been cancelled and its Inventory
 * restored.
 */
final class OrderWasCancelled
{
    public function __construct(
        public readonly Order $order,
    ) {
    }
}
