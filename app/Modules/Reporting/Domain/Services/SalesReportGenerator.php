<?php

namespace App\Modules\Reporting\Domain\Services;

/**
 * Pure, framework-free — the single formula owner for "what does the
 * Sales Report actually say", the same shape Commerce's PricingService/
 * Loyalty's PointsCalculationService already establish: only knows how
 * to combine numbers it's given (already aggregated by
 * `SalesQueryBuilder` — SUM/COUNT/GROUP BY all happen in SQL, never a
 * PHP loop over raw Order rows here). `average_order_value` is the one
 * real business formula this report needs — integer division, rounding
 * down, since a fractional cent average is meaningless (Money-as-Integer,
 * HANDOFF gotcha #4).
 */
final class SalesReportGenerator
{
    /**
     * @param array<string, int> $salesByDay day (Y-m-d) => total sales (cents)
     * @return array{totalSales: int, totalOrders: int, averageOrderValue: int, salesByDay: array<string, int>}
     */
    public function generate(int $totalSales, int $totalOrders, array $salesByDay): array
    {
        return [
            'totalSales' => $totalSales,
            'totalOrders' => $totalOrders,
            'averageOrderValue' => $totalOrders > 0 ? intdiv($totalSales, $totalOrders) : 0,
            'salesByDay' => $salesByDay,
        ];
    }
}
