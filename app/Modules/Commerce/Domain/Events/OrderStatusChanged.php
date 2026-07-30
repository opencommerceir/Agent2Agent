<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Order;
use App\Modules\Commerce\Domain\ValueObjects\OrderStatus;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched by UpdateOrderStatusAction after a generic (non-cancel)
 * status transition. previousStatus is carried explicitly since the
 * Order entity itself no longer holds it once changeStatus() has run.
 */
final class OrderStatusChanged
{
    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $previousStatus,
    ) {
    }
}
