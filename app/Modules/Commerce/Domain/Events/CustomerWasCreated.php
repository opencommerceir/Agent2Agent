<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Customer;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a Customer has been persisted.
 */
final class CustomerWasCreated
{
    public function __construct(
        public readonly Customer $customer,
    ) {
    }
}
