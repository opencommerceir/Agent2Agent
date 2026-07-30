<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Domain\Events\OrderWasCancelled;
use App\Modules\Commerce\Domain\Exceptions\OrderNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class CancelOrderAction
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    public function execute(int $id, int $tenantId): OrderData
    {
        return DB::transaction(function () use ($id, $tenantId) {
            $order = $this->orders->findById($id, $tenantId);

            if (! $order) {
                throw new OrderNotFoundException("Order [{$id}] does not exist.");
            }

            $order->cancel(); // throws OrderAlreadyCancelledException / InvalidOrderStatusException

            foreach ($order->items() as $item) {
                $inventory = $this->inventories->findByProduct($item->productId(), $tenantId);

                if ($inventory) {
                    $inventory->restore($item->quantity());
                    $this->inventories->save($inventory);
                }
            }

            $order = $this->orders->save($order);

            Event::dispatch(new OrderWasCancelled($order));

            return OrderData::fromEntity($order);
        });
    }
}
