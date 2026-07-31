<?php

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Finance\Application\DTOs\TaxRateData;
use App\Modules\Finance\Domain\Entities\TaxRate;
use App\Modules\Finance\Domain\Exceptions\InvalidTaxRateException;
use App\Modules\Finance\Domain\Repositories\TaxRateRepositoryInterface;
use App\Modules\Finance\Domain\ValueObjects\TaxRegion;

/**
 * Region uniqueness is enforced per-tenant, the same "SKU/Category-slug/
 * Tag-name are unique per tenant, not globally" convention every other
 * named aggregate in this codebase follows. `region: "DEFAULT"` is a
 * legitimate, documented value — it registers this tenant's fallback
 * rate (TaxRegion::default(), used by CommerceTaxRateProvider when no
 * rate exists for a more specific region).
 */
final class CreateTaxRateAction
{
    public function __construct(
        private readonly TaxRateRepositoryInterface $taxRates,
    ) {
    }

    public function execute(int $tenantId, string $region, int $ratePercentage): TaxRateData
    {
        $regionValue = new TaxRegion($region); // throws InvalidArgumentException on bad format

        if ($this->taxRates->regionExists($regionValue, $tenantId)) {
            throw new InvalidTaxRateException("A tax rate for region [{$regionValue}] already exists for this tenant.");
        }

        $taxRate = TaxRate::create($tenantId, $regionValue, $ratePercentage); // throws InvalidArgumentException if out of range

        $taxRate = $this->taxRates->save($taxRate);

        return TaxRateData::fromEntity($taxRate);
    }
}
