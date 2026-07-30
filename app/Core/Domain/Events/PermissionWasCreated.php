<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\Permission;

final class PermissionWasCreated
{
    public function __construct(
        public readonly Permission $permission,
    ) {
    }
}
