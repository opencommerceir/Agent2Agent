<?php

namespace App\Modules\Reporting\Infrastructure\Queries;

use App\Modules\Commerce\Infrastructure\Models\Order;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;

/**
 * See SalesQueryBuilder's own docblock for the full reasoning behind
 * this module querying Commerce's Eloquent Models directly.
 *
 * Orders with no `customer_id` (optional since Commerce Stage 4) are
 * excluded — there is no Customer to rank.
 */
final class TopCustomersQueryBuilder
{
    private const EXCLUDED_STATUSES = ['cancelled', 'refunded'];

    /**
     * @return list<array{customer_id: int, total_orders: int, total_spent: int}>
     */
    public function top(int $tenantId, DateRange $dateRange, int $limit): array
    {
        $rows = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->whereBetween('created_at', [$dateRange->start(), $dateRange->end()])
            ->groupBy('customer_id')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->selectRaw('customer_id, COUNT(*) as total_orders, SUM(total_amount) as total_spent')
            ->get();

        return $rows->map(fn ($row) => [
            'customer_id' => (int) $row->customer_id,
            'total_orders' => (int) $row->total_orders,
            'total_spent' => (int) $row->total_spent,
        ])->all();
    }
}
