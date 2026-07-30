<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Order;
use App\Modules\Commerce\Domain\ValueObjects\OrderStatus;

interface OrderRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Order;

    public function orderNumberExists(string $orderNumber, int $tenantId): bool;

    /**
     * @return list<Order>
     */
    public function listByTenant(int $tenantId, ?OrderStatus $status, int $limit): array;

    public function save(Order $order): Order;
}
