<?php

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Finance\Domain\Exceptions\TaxRateNotFoundException;
use App\Modules\Finance\Domain\Repositories\TaxRateRepositoryInterface;
use App\Modules\Finance\Domain\Services\TaxCalculationService;
use App\Modules\Finance\Domain\ValueObjects\Money;
use App\Modules\Finance\Domain\ValueObjects\TaxRegion;

/**
 * Backs the `finance.tax.calculate` MCP capability — a strict, explicit
 * calculation: the caller names a real, active region or gets
 * TaxRateNotFoundException, with no fallback chain. This is the
 * intentional difference from CommerceTaxRateProvider (used internally
 * by Commerce's own checkout pricing), which silently falls back through
 * the tenant's DEFAULT region and finally to Commerce's own hardcoded
 * rate rather than ever failing — two different policies for two
 * different callers, not one reducible to the other.
 */
final class CalculateTaxAction
{
    public function __construct(
        private readonly TaxRateRepositoryInterface $taxRates,
        private readonly TaxCalculationService $calculator,
    ) {
    }

    /**
     * @return array{tax_amount: int, total_amount: int}
     */
    public function execute(int $tenantId, int $amount, string $currency, string $region): array
    {
        $taxRate = $this->taxRates->findByRegion(new TaxRegion($region), $tenantId);

        if (! $taxRate || ! $taxRate->isActive()) {
            throw new TaxRateNotFoundException("No active tax rate configured for region [{$region}].");
        }

        $subtotal = Money::fromAmount($amount, $currency);
        $tax = $this->calculator->calculateTax($subtotal, $taxRate);
        $total = $this->calculator->calculateTotal($subtotal, $tax);

        return [
            'tax_amount' => $tax->amount(),
            'total_amount' => $total->amount(),
        ];
    }
}
