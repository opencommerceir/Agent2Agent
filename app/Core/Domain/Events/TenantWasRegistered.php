<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\Tenant;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a Tenant has been persisted.
 */
final class TenantWasRegistered
{
    public function __construct(
        public readonly Tenant $tenant,
    ) {
    }
}
