<?php

namespace App\Modules\Analytics\Domain\Services;

use DateTimeImmutable;

/**
 * Owns `KPIType::{TotalCustomers,NewCustomers,CustomerRetentionRate,
 * CustomerLifetimeValue}`. Pure — counts/divides numbers `CalculateKPIAction`
 * already fetched (via `CustomerRepositoryInterface::listByTenant()` for
 * the first two, Reporting's own `TopCustomersQueryBuilder`/
 * `RevenueQueryBuilder` for the last two).
 *
 * `'retention_rate'`/`'lifetime_value'` are both **documented
 * simplifications**, not a cohort-based or predictive model — the same
 * "real, working, honestly-scoped-down" precedent `ExpirePointsAction`'s
 * own simplified FIFO (HANDOFF §7.10/§8.26) and Reporting's own
 * `active_accounts` snapshot definition (§7.11) already set:
 * - Retention Rate = (customers with >1 Order in the period) / (all
 *   customers with >=1 Order in the period), from `TopCustomersQueryBuilder`'s
 *   own `total_orders` column — not a true repeat-*visitor* rate across
 *   longer horizons.
 * - Lifetime Value = period revenue / distinct ordering customers in that
 *   same period — an average-order-value-per-customer figure, not a
 *   discounted future-value CLV model.
 */
final class CustomerCalculator implements KPICalculatorInterface
{
    public function calculate(array $input): array
    {
        return match ($input['metric']) {
            'total' => ['count' => count($input['customerCreatedAt'])],
            'new' => ['count' => $this->countNew($input['customerCreatedAt'], $input['periodStart'], $input['periodEnd'])],
            'retention_rate' => ['retentionRatePercent' => $this->percent($input['repeatCustomers'], $input['totalCustomers'])],
            'lifetime_value' => ['amountCents' => $this->average($input['totalRevenueCents'], $input['totalCustomers'])],
        };
    }

    /**
     * @param list<DateTimeImmutable> $customerCreatedAt
     */
    private function countNew(array $customerCreatedAt, DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): int
    {
        return count(array_filter(
            $customerCreatedAt,
            fn (DateTimeImmutable $createdAt) => $createdAt >= $periodStart && $createdAt <= $periodEnd,
        ));
    }

    private function percent(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 2) : 0.0;
    }

    private function average(int $totalRevenueCents, int $totalCustomers): int
    {
        return $totalCustomers > 0 ? intdiv($totalRevenueCents, $totalCustomers) : 0;
    }
}
