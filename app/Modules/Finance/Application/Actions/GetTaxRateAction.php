<?php

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Finance\Application\DTOs\TaxRateData;
use App\Modules\Finance\Domain\Exceptions\TaxRateNotFoundException;
use App\Modules\Finance\Domain\Repositories\TaxRateRepositoryInterface;
use App\Modules\Finance\Domain\ValueObjects\TaxRegion;

/**
 * Backs the `finance.tax.get` MCP capability — looked up by region
 * (the capability's own input shape), not by id.
 */
final class GetTaxRateAction
{
    public function __construct(
        private readonly TaxRateRepositoryInterface $taxRates,
    ) {
    }

    public function execute(int $tenantId, string $region): TaxRateData
    {
        $taxRate = $this->taxRates->findByRegion(new TaxRegion($region), $tenantId);

        if (! $taxRate) {
            throw new TaxRateNotFoundException("No tax rate configured for region [{$region}].");
        }

        return TaxRateData::fromEntity($taxRate);
    }
}
