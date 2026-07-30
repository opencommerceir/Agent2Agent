<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Domain\Entities\Order;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\OrderStatus;

/**
 * Backs the `commerce.order.list` MCP capability — takes the raw
 * `array $input` MCP Gateway received plus tenantId, doubling directly
 * as the callable CommerceServiceProvider::boot() registers, the same
 * pattern ListProductsAction established.
 */
final class ListOrdersAction
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{orders: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $status = isset($input['status']) && is_string($input['status'])
            ? OrderStatus::tryFrom($input['status'])
            : null;

        $limit = isset($input['limit']) && is_int($input['limit'])
            ? max(1, min($input['limit'], self::MAX_LIMIT))
            : self::DEFAULT_LIMIT;

        $orders = $this->orders->listByTenant($tenantId, $status, $limit);

        return [
            'orders' => array_map(
                fn (Order $order) => OrderData::fromEntity($order)->toArray(),
                $orders,
            ),
        ];
    }
}
