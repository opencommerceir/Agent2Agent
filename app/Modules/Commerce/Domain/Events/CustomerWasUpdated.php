<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Customer;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a Customer's changes have been persisted.
 */
final class CustomerWasUpdated
{
    public function __construct(
        public readonly Customer $customer,
    ) {
    }
}
