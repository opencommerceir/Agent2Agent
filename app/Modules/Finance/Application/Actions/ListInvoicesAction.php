<?php

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Finance\Application\DTOs\InvoiceData;
use App\Modules\Finance\Domain\Entities\Invoice;
use App\Modules\Finance\Domain\Repositories\InvoiceRepositoryInterface;
use App\Modules\Finance\Domain\ValueObjects\InvoiceStatus;

/**
 * Backs the `finance.invoice.list` MCP capability — takes the raw
 * `array $input` MCP Gateway received plus tenantId, the same pattern
 * every other List*Action in this codebase established.
 */
final class ListInvoicesAction
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{invoices: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $status = isset($input['status']) && is_string($input['status'])
            ? InvoiceStatus::tryFrom($input['status'])
            : null;

        $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : null;

        $limit = isset($input['limit']) && is_int($input['limit'])
            ? max(1, min($input['limit'], self::MAX_LIMIT))
            : self::DEFAULT_LIMIT;

        $invoices = $this->invoices->list($tenantId, $status, $customerId, $limit);

        return [
            'invoices' => array_map(
                fn (Invoice $invoice) => InvoiceData::fromEntity($invoice)->toArray(),
                $invoices,
            ),
        ];
    }
}
