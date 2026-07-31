<?php

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Finance\Application\DTOs\TaxRateData;
use App\Modules\Finance\Domain\Events\TaxRateWasUpdated;
use App\Modules\Finance\Domain\Exceptions\TaxRateNotFoundException;
use App\Modules\Finance\Domain\Repositories\TaxRateRepositoryInterface;
use Illuminate\Support\Facades\Event;

/**
 * region is deliberately not updatable here — same reasoning Product's
 * SKU isn't: it is the TaxRate's business identity (the thing
 * region-uniqueness is enforced against). Changing it would need a
 * distinct, more deliberate operation than a generic field update.
 *
 * Not wired to MCP this stage — no `finance.tax.update`-shaped
 * capability was among the 8 requested (only create/get/list/calculate
 * were, for tax rates). Exercised directly in tests instead, the same
 * "built, tested, not yet exposed to Agents" gap several Commerce/CRM
 * Actions already carry.
 */
final class UpdateTaxRateAction
{
    public function __construct(
        private readonly TaxRateRepositoryInterface $taxRates,
    ) {
    }

    public function execute(int $id, int $tenantId, int $ratePercentage, bool $isActive): TaxRateData
    {
        $taxRate = $this->taxRates->findById($id, $tenantId);

        if (! $taxRate) {
            throw new TaxRateNotFoundException("Tax rate [{$id}] does not exist.");
        }

        $taxRate->update($ratePercentage, $isActive); // throws InvalidArgumentException if out of range

        $taxRate = $this->taxRates->save($taxRate);

        Event::dispatch(new TaxRateWasUpdated($taxRate));

        return TaxRateData::fromEntity($taxRate);
    }
}
