<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Domain\Exceptions\OrderNotFoundException;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;

/**
 * Orders are tenant-wide resources once placed (unlike Cart, which is
 * strictly per-Agent) — any Agent authorized in the tenant can look up
 * any of the tenant's Orders, not only ones it placed itself. Matches a
 * real fulfillment scenario where a different Agent/service manages
 * Orders another Agent's shopper placed.
 */
final class GetOrderAction
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {
    }

    public function execute(int $id, int $tenantId): OrderData
    {
        $order = $this->orders->findById($id, $tenantId);

        if (! $order) {
            throw new OrderNotFoundException("Order [{$id}] does not exist.");
        }

        return OrderData::fromEntity($order);
    }
}
