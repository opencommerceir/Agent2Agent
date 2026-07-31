<?php

namespace App\Modules\Reporting\Infrastructure\Queries;

use App\Modules\Commerce\Infrastructure\Models\Order;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use Illuminate\Support\Facades\DB;

/**
 * See SalesQueryBuilder's own docblock for the full reasoning behind
 * this module querying Commerce's Eloquent Models directly.
 *
 * Only counts an Order toward revenue if it has at least one `completed`
 * Payment (rule §e.4: "از Order و Payment repositories استفاده کن") —
 * an Order that was placed but never actually paid for (still pending, a
 * failed charge, ...) contributed no real revenue. Uses `whereExists`
 * rather than a plain JOIN against `payments` specifically to avoid
 * double-counting an Order's own subtotal/tax/discount if it somehow has
 * more than one `completed` Payment row (a JOIN would multiply the
 * Order's amounts once per matching Payment row; `whereExists` only
 * asks "does at least one exist").
 */
final class RevenueQueryBuilder
{
    private const COMPLETED_PAYMENT_STATUS = 'completed';

    /**
     * @return array{gross_revenue: int, tax_collected: int, discounts_applied: int}
     */
    public function totals(int $tenantId, DateRange $dateRange): array
    {
        $row = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateRange->start(), $dateRange->end()])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('payments')
                    ->whereColumn('payments.order_id', 'orders.id')
                    ->where('payments.status', self::COMPLETED_PAYMENT_STATUS);
            })
            ->selectRaw(
                'COALESCE(SUM(subtotal_amount), 0) as gross_revenue, '
                .'COALESCE(SUM(tax_amount), 0) as tax_collected, '
                .'COALESCE(SUM(discount_amount), 0) as discounts_applied',
            )
            ->first();

        return [
            'gross_revenue' => (int) $row->gross_revenue,
            'tax_collected' => (int) $row->tax_collected,
            'discounts_applied' => (int) $row->discounts_applied,
        ];
    }
}
