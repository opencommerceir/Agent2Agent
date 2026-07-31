<?php

namespace App\Modules\Finance\Infrastructure\Services;

use App\Modules\Commerce\Application\Services\TaxRateProviderInterface;
use App\Modules\Finance\Domain\Repositories\TaxRateRepositoryInterface;
use App\Modules\Finance\Domain\ValueObjects\TaxRegion;

/**
 * Finance's answer to Commerce's own TaxRateProviderInterface —
 * Commerce defines the contract (in Commerce\Application\Services, never
 * imported by Finance's Domain/Application layers, only by this one
 * Infrastructure adapter), Finance implements it using its own
 * TaxRateRepositoryInterface. Bound over Commerce's default
 * NullTaxRateProvider by FinanceServiceProvider::register() — this is
 * the *only* class in the entire Finance module that references anything
 * under `App\Modules\Commerce\*`, and it references only Commerce's own
 * published Application-layer Interface, never a Commerce Domain Entity,
 * Infrastructure Model, or Exception class.
 *
 * The 3-tier fallback CalculatePricingAction/ProcessPaymentAction expect
 * lives here, not in Finance's own TaxCalculationService (which stays
 * pure — no fallback policy, no Repository dependency, just a formula):
 * 1. an active TaxRate for the given region, if one was given and exists;
 * 2. else the tenant's TaxRegion::default() row, if active;
 * 3. else null — Commerce's own Actions interpret that as "fall back to
 *    the hardcoded 9%", a policy this class deliberately knows nothing
 *    about (that constant belongs to Commerce, not Finance).
 */
final class CommerceTaxRateProvider implements TaxRateProviderInterface
{
    public function __construct(
        private readonly TaxRateRepositoryInterface $taxRates,
    ) {
    }

    public function getRatePercent(int $tenantId, ?string $region): ?float
    {
        if ($region !== null) {
            $rate = $this->taxRates->findByRegion(new TaxRegion($region), $tenantId);

            if ($rate !== null && $rate->isActive()) {
                return $rate->ratePercentage() / 100;
            }
        }

        $default = $this->taxRates->findByRegion(TaxRegion::default(), $tenantId);

        if ($default !== null && $default->isActive()) {
            return $default->ratePercentage() / 100;
        }

        return null;
    }
}
