<?php

namespace App\Modules\Finance\Domain\Events;

use App\Modules\Finance\Domain\Entities\TaxRate;

/**
 * Domain event: a fact that already happened. Dispatched after a
 * TaxRate's rate/active-state has been changed and persisted (not on
 * creation — see CreateTaxRateAction, which has no event of its own
 * requested this stage).
 */
final class TaxRateWasUpdated
{
    public function __construct(
        public readonly TaxRate $taxRate,
    ) {
    }
}
