<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\Permission;
use App\Core\Domain\Entities\Role;

final class PermissionAssignedToRole
{
    public function __construct(
        public readonly Role $role,
        public readonly Permission $permission,
    ) {
    }
}
