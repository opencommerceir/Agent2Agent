<?php

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Finance\Application\DTOs\InvoiceData;
use App\Modules\Finance\Domain\Events\InvoiceWasIssued;
use App\Modules\Finance\Domain\Exceptions\InvoiceNotFoundException;
use App\Modules\Finance\Domain\Repositories\InvoiceRepositoryInterface;
use Illuminate\Support\Facades\Event;

/**
 * Backs the `finance.invoice.issue` MCP capability. Invoice::issue()
 * itself enforces the Draft -> Issued transition (throws
 * InvalidArgumentException otherwise), so this Action is only ever a
 * thin findById -> issue -> save -> dispatch wrapper, the same shape
 * Commerce's UpdateOrderStatusAction and CRM's UpdateTicketAction take.
 */
final class IssueInvoiceAction
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

        $invoice->issue();

        $invoice = $this->invoices->save($invoice);

        Event::dispatch(new InvoiceWasIssued($invoice));

        return InvoiceData::fromEntity($invoice);
    }
}
