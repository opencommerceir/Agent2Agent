<?php

namespace App\Modules\Reporting\Infrastructure\Queries;

use App\Modules\Commerce\Infrastructure\Models\Order;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;

/**
 * **The one deliberate, documented exception to this codebase's
 * Module -> Module rule** ("depend on the other module's Domain
 * Repository *Interface*, never its Infrastructure/Model" — HANDOFF §3
 * item 8, established by CRM/Finance/Workflows/Loyalty and followed
 * everywhere else in Reporting itself, e.g. every Generator resolves a
 * product/customer *name* through `ProductRepositoryInterface`/
 * `CustomerRepositoryInterface`, never a Model).
 *
 * Query Builders are different: their entire reason to exist is
 * computing SUM/COUNT/GROUP BY aggregates across potentially many rows
 * (rule §e: "از Eloquent aggregates استفاده کن, نه loop در PHP") — doing
 * that through `OrderRepositoryInterface::listByTenant()` would mean
 * fetching every matching Order as a full Domain Entity and summing in
 * a PHP loop, exactly the anti-pattern this stage's own rule forbids.
 * The Repository Interfaces those other modules publish are shaped
 * around entity retrieval by id ("give me the Order with this id"), not
 * around report-shaped aggregate questions ("what's the SUM of every
 * Order's total in this date range, grouped by day") — extending
 * `OrderRepositoryInterface` with five report-specific aggregate methods
 * would leak Reporting's query shapes into a write-side Domain contract
 * that Commerce itself doesn't need.
 *
 * This is safe *specifically* because every Query Builder in this
 * module is SELECT-only — Reporting never writes to another module's
 * table, so there is no risk of it corrupting Commerce/Loyalty's own
 * invariants, only a real, accepted coupling to their current schema:
 * if `orders`'/`point_transactions`' columns are ever renamed, these
 * five classes (and only these five) are what would need to change.
 * This is the standard CQRS "Read Model" shape — a read-only projection
 * is allowed to cut across aggregate boundaries that a write operation
 * never could.
 *
 * `Cancelled`/`Refunded` orders are excluded from every query in this
 * module (mirrors `OrderStatus::Cancelled`/`Refunded`'s values without
 * importing Commerce's enum — a Query Builder works in raw column
 * values, not Domain VOs) — a cancelled or refunded Order was never a
 * real, completed sale.
 */
final class SalesQueryBuilder
{
    private const EXCLUDED_STATUSES = ['cancelled', 'refunded'];

    /**
     * @return array{total_sales: int, total_orders: int}
     */
    public function totals(int $tenantId, DateRange $dateRange): array
    {
        $row = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->whereBetween('created_at', [$dateRange->start(), $dateRange->end()])
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_sales, COUNT(*) as total_orders')
            ->first();

        return [
            'total_sales' => (int) $row->total_sales,
            'total_orders' => (int) $row->total_orders,
        ];
    }

    /**
     * @return array<string, int> day (Y-m-d) => total sales (cents)
     */
    public function byDay(int $tenantId, DateRange $dateRange): array
    {
        $rows = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->whereBetween('created_at', [$dateRange->start(), $dateRange->end()])
            ->selectRaw('DATE(created_at) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $byDay = [];

        foreach ($rows as $row) {
            $byDay[$row->day] = (int) $row->total;
        }

        return $byDay;
    }
}
