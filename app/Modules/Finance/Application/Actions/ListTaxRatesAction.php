<?php

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Finance\Application\DTOs\TaxRateData;
use App\Modules\Finance\Domain\Entities\TaxRate;
use App\Modules\Finance\Domain\Repositories\TaxRateRepositoryInterface;

/**
 * Backs the `finance.tax.list` MCP capability — takes the raw
 * `array $input` MCP Gateway received plus tenantId, the same pattern
 * Commerce's ListProductsAction/ListOrdersAction and CRM's
 * ListTicketsAction established.
 */
final class ListTaxRatesAction
{
    public function __construct(
        private readonly TaxRateRepositoryInterface $taxRates,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{tax_rates: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $isActive = isset($input['is_active']) && is_bool($input['is_active'])
            ? $input['is_active']
            : null;

        $taxRates = $this->taxRates->list($tenantId, $isActive);

        return [
            'tax_rates' => array_map(
                fn (TaxRate $taxRate) => TaxRateData::fromEntity($taxRate)->toArray(),
                $taxRates,
            ),
        ];
    }
}
