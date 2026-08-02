<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Order;
use App\Modules\Commerce\Domain\ValueObjects\OrderStatus;
use DateTimeImmutable;

interface OrderRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Order;

    public function orderNumberExists(string $orderNumber, int $tenantId): bool;

    /**
     * $from/$to (Phase 5, Stage 3 — Bulk Operations, §7.23) are an
     * optional trailing pair, the same "widen, don't duplicate" shape
     * every other cross-stage Repository extension in this codebase has
     * used — omitted, every existing caller (the Dashboard's own
     * OrderController, `commerce.order.list`'s own handler) is
     * unaffected; given, `ExportOrdersAction` filters to Orders created
     * within that inclusive window, matching against `createdAt` (the
     * only date this Repository already exposes on the Entity — there is
     * no separate "order date" concept).
     */
    public function listByTenant(
        int $tenantId,
        ?OrderStatus $status,
        int $limit,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
    ): array;

    /**
     * @return list<Order>
     */
    public function listByCustomer(int $customerId, int $tenantId, int $limit): array;

    public function save(Order $order): Order;
}
