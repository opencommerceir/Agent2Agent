<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\Permission;
use App\Core\Domain\Entities\Role;

/**
 * Not explicitly requested, but added for symmetry: MemberAdded/Removed
 * both have events, so Assigned/Revoked should too — otherwise revoking a
 * permission would be a silent operation with no audit trail, unlike
 * every other state change in this module.
 */
final class PermissionRevokedFromRole
{
    public function __construct(
        public readonly Role $role,
        public readonly Permission $permission,
    ) {
    }
}
