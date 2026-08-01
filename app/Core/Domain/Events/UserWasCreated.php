<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\User;

/**
 * Domain event: a fact that already happened. Dispatched after a User has
 * been persisted, mirroring `TenantWasRegistered`'s own shape.
 */
final class UserWasCreated
{
    public function __construct(
        public readonly User $user,
    ) {
    }
}
