<?php

namespace App\Modules\Analytics\Domain\Services;

/**
 * Owns `KPIType::TotalOrders` (near-passthrough, same reasoning
 * `RevenueCalculator`'s own docblock gives for its own `'total'` metric)
 * and `KPIType::AverageOrderValue` — the one genuinely new derived number
 * in this pair, integer division (rounds down, same convention every
 * Money-shaped calculation in this codebase already uses — never a
 * float), 0 rather than a division error when there were no orders in
 * the period at all.
 */
final class OrderCalculator implements KPICalculatorInterface
{
    public function calculate(array $input): array
    {
        return match ($input['metric']) {
            'total' => ['count' => $input['totalOrders']],
            'average_order_value' => ['amountCents' => $this->average($input['totalRevenueCents'], $input['totalOrders'])],
        };
    }

    private function average(int $totalRevenueCents, int $totalOrders): int
    {
        return $totalOrders > 0 ? intdiv($totalRevenueCents, $totalOrders) : 0;
    }
}
