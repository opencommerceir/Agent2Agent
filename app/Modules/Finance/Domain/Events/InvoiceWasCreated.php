<?php

namespace App\Modules\Finance\Domain\Events;

use App\Modules\Finance\Domain\Entities\Invoice;

/**
 * Domain event: a fact that already happened. Dispatched after an
 * Invoice has been persisted.
 */
final class InvoiceWasCreated
{
    public function __construct(
        public readonly Invoice $invoice,
    ) {
    }
}
