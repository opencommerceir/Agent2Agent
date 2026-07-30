<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Domain\Events\OrderStatusChanged;
use App\Modules\Commerce\Domain\Exceptions\OrderNotFoundException;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\OrderStatus;
use Illuminate\Support\Facades\Event;

/**
 * The generic fulfillment-pipeline transition (Pending/Confirmed/
 * Processing/Shipped/Delivered) — Order::changeStatus() itself refuses
 * Cancelled/Refunded as a target, so this can never bypass
 * CancelOrderAction's inventory-restoring side effect.
 */
final class UpdateOrderStatusAction
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {
    }

    public function execute(int $id, int $tenantId, string $status): OrderData
    {
        $order = $this->orders->findById($id, $tenantId);

        if (! $order) {
            throw new OrderNotFoundException("Order [{$id}] does not exist.");
        }

        $previousStatus = $order->status();

        $order->changeStatus(OrderStatus::from($status)); // throws InvalidOrderStatusException, or ValueError for an unknown status string

        $order = $this->orders->save($order);

        Event::dispatch(new OrderStatusChanged($order, $previousStatus));

        return OrderData::fromEntity($order);
    }
}
