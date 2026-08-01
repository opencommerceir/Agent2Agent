<?php

namespace App\Modules\Analytics\Domain\Services;

/**
 * Owns `KPIType::Revenue` and `KPIType::RevenueGrowthRate`. Pure,
 * framework-free — the raw revenue totals themselves come from
 * Reporting's own `RevenueQueryBuilder` (a SQL aggregate — rule §e "از
 * Eloquent aggregates استفاده کن, نه loop در PHP"), fetched by
 * `CalculateKPIAction` before this class ever runs.
 *
 * `metric: 'total'` is a near-passthrough (still routed through here, not
 * inlined in the Action, so every KPI computation — including the ones
 * with no real derived math — goes through the same `KPICalculatorInterface`
 * shape uniformly). `metric: 'growth_rate'` is the real derived
 * computation: percent change between the current and a prior period's
 * revenue, `null` (not a divide-by-zero crash or a misleading 0%) when
 * the prior period had no revenue to compare against at all.
 */
final class RevenueCalculator implements KPICalculatorInterface
{
    public function calculate(array $input): array
    {
        return match ($input['metric']) {
            'total' => ['amountCents' => $input['grossRevenueCents']],
            'growth_rate' => ['growthRatePercent' => $this->growthRate(
                $input['currentPeriodCents'],
                $input['previousPeriodCents'],
            )],
        };
    }

    private function growthRate(int $currentCents, int $previousCents): ?float
    {
        if ($previousCents === 0) {
            return null;
        }

        return round((($currentCents - $previousCents) / $previousCents) * 100, 2);
    }
}
