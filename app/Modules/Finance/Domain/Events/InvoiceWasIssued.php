<?php

namespace App\Modules\Finance\Domain\Events;

use App\Modules\Finance\Domain\Entities\Invoice;

/**
 * Domain event: a fact that already happened. Dispatched after an
 * Invoice has moved from Draft to Issued and been persisted.
 */
final class InvoiceWasIssued
{
    public function __construct(
        public readonly Invoice $invoice,
    ) {
    }
}
