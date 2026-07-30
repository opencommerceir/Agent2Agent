<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\Role;

final class RoleWasCreated
{
    public function __construct(
        public readonly Role $role,
    ) {
    }
}
