<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Domain\Entities\Order;
use App\Modules\Commerce\Domain\Exceptions\CustomerNotFoundException;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;

/**
 * Where Customer and Order — two separate aggregates in the same
 * Commerce module — meet: only through explicit ids and each
 * aggregate's own Repository interface, never a direct object reference
 * between a Customer and an Order entity (Dependency Inversion, per this
 * stage's explicit request). Verifies the Customer exists first so a
 * typo'd id reports CustomerNotFoundException rather than silently
 * returning an empty order list indistinguishable from "no orders yet".
 */
final class GetCustomerOrdersAction
{
    private const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly OrderRepositoryInterface $orders,
    ) {
    }

    /**
     * @return array{orders: list<array<string, mixed>>}
     */
    public function execute(int $customerId, int $tenantId, int $limit = self::DEFAULT_LIMIT): array
    {
        if (! $this->customers->findById($customerId, $tenantId)) {
            throw new CustomerNotFoundException("Customer [{$customerId}] does not exist.");
        }

        $orders = $this->orders->listByCustomer($customerId, $tenantId, $limit);

        return [
            'orders' => array_map(
                fn (Order $order) => OrderData::fromEntity($order)->toArray(),
                $orders,
            ),
        ];
    }
}
