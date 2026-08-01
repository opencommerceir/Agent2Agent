<?php

namespace App\Modules\Analytics\Domain\Services;

/**
 * Owns `KPIType::ConversionRate` — Cart -> Order, the ratio of completed
 * Orders to Carts created in the same period. `totalCarts` comes from the
 * new `CartRepositoryInterface::countCreatedBetween()` (added unprompted
 * this stage — nothing before this needed a bare count of Carts created
 * in a window, only single-Cart lookups or the abandoned-Cart scan
 * existed, HANDOFF §3 pattern #12). 0% (not a division error) when no
 * Carts were created in the period at all.
 */
final class ConversionRateCalculator implements KPICalculatorInterface
{
    public function calculate(array $input): array
    {
        $totalCarts = $input['totalCarts'];
        $totalOrders = $input['totalOrders'];

        return [
            'conversionRatePercent' => $totalCarts > 0 ? round(($totalOrders / $totalCarts) * 100, 2) : 0.0,
        ];
    }
}
