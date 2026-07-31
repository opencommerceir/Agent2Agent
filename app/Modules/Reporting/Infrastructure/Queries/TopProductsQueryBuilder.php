<?php

namespace App\Modules\Reporting\Infrastructure\Queries;

use App\Modules\Commerce\Infrastructure\Models\OrderItem;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;

/**
 * See SalesQueryBuilder's own docblock for the full reasoning behind
 * this module querying Commerce's Eloquent Models directly (a
 * deliberate, read-only CQRS-style exception to the usual Module ->
 * Module Repository Interface rule).
 *
 * Ranking (`ORDER BY quantity_sold DESC`) and the `limit` cap both
 * happen in SQL, not PHP — the GROUP BY + JOIN across every matching
 * order_items row is the expensive part this query optimizes; sorting
 * an unlimited PHP array afterward would defeat the point.
 */
final class TopProductsQueryBuilder
{
    private const EXCLUDED_STATUSES = ['cancelled', 'refunded'];

    /**
     * @return list<array{product_id: int, quantity_sold: int, total_revenue: int}>
     */
    public function top(int $tenantId, DateRange $dateRange, int $limit): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNotIn('orders.status', self::EXCLUDED_STATUSES)
            ->whereBetween('orders.created_at', [$dateRange->start(), $dateRange->end()])
            ->groupBy('order_items.product_id')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->selectRaw(
                'order_items.product_id as product_id, '
                .'SUM(order_items.quantity) as quantity_sold, '
                .'SUM(order_items.total_price_amount) as total_revenue',
            )
            ->get();

        return $rows->map(fn ($row) => [
            'product_id' => (int) $row->product_id,
            'quantity_sold' => (int) $row->quantity_sold,
            'total_revenue' => (int) $row->total_revenue,
        ])->all();
    }
}
