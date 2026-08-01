<?php

namespace App\Modules\Analytics\Domain\Services;

/**
 * The contract each of the 4 requested Domain Calculators
 * (`RevenueCalculator`/`OrderCalculator`/`CustomerCalculator`/
 * `ConversionRateCalculator`) implements. Deliberately a loose
 * `array -> array` shape, not a rigid per-KPIType signature: unlike
 * Reporting's own Generators (each a single, fixed computation),
 * these 4 Calculators each own a small *group* of related KPIs that
 * share underlying inputs (e.g. `RevenueCalculator` owns both `Revenue`
 * and `RevenueGrowthRate`) — unifying that into one polymorphic method
 * per Calculator matches this codebase's own precedent of pure,
 * framework-free Domain Services (`PricingService`, `WorkflowEvaluator`,
 * `TemplateRenderer`) that only combine numbers/data they're already
 * given, never fetch anything themselves. `CalculateKPIAction`
 * (Application layer) is what fetches the raw aggregates (via
 * Reporting's own Query Builders — see that Action's own docblock) and
 * builds the `$input` array each Calculator expects; every concrete
 * class documents its own exact `$input`/return shape per `metric`.
 */
interface KPICalculatorInterface
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array;
}
