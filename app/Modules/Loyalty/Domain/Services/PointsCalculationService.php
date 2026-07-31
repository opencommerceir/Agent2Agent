<?php

namespace App\Modules\Loyalty\Domain\Services;

/**
 * Pure, framework-free — the single formula owner for "how many points
 * does this purchase earn" (rule §d.6: "$1 = 100 cents = 1 point"), the
 * same shape Commerce's PricingService/Finance's TaxCalculationService/
 * Workflows' WorkflowEvaluator already establish: only knows how to
 * combine numbers it's given, never decides which order/amount qualifies.
 *
 * Integer division, always rounding down — a $1.50 order earns 1 point,
 * not 1.5 (HANDOFF gotcha #4: JSON/points never carry a fractional
 * value). OrderPlacedListener is the only caller this stage.
 */
final class PointsCalculationService
{
    private const CENTS_PER_POINT = 100;

    public function calculateForAmount(int $amountInCents): int
    {
        return intdiv(max(0, $amountInCents), self::CENTS_PER_POINT);
    }
}
