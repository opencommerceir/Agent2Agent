<?php

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Finance\Application\DTOs\InvoiceData;
use App\Modules\Finance\Domain\Exceptions\InvoiceNotFoundException;
use App\Modules\Finance\Domain\Repositories\InvoiceRepositoryInterface;

/**
 * Backs the `finance.invoice.get` MCP capability. Tenant-scoped by
 * InvoiceRepositoryInterface::findById() itself — an id belonging to a
 * different tenant reports the same InvoiceNotFoundException as an id
 * that never existed at all, never a distinguishable "forbidden" (same
 * tenant-isolation-by-omission shape every other findById() in this
 * codebase uses).
 */
final class GetInvoiceAction
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
    ) {
    }

    public function execute(int $id, int $tenantId): InvoiceData
    {
        $invoice = $this->invoices->findById($id, $tenantId);

        if (! $invoice) {
            throw new InvoiceNotFoundException("Invoice [{$id}] does not exist.");
        }

        return InvoiceData::fromEntity($invoice);
    }
}
