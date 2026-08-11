<?php

namespace App\Domains\Nexus\Business\Domain\Events;

use App\Domains\Nexus\Business\Domain\Entities\Business;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a Business has been persisted.
 */
final class BusinessWasRegistered
{
    public function __construct(
        public readonly Business $business,
    ) {
    }
}
