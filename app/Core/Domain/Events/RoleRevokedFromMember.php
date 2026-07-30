<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\MemberRole;

/**
 * Added for the same symmetry reason as PermissionRevokedFromRole.
 * Also the event RevokeRoleFromMemberAction fires on every cascade
 * triggered by RevokeRolesWhenMemberRemovedFromOrganization.
 */
final class RoleRevokedFromMember
{
    public function __construct(
        public readonly MemberRole $memberRole,
    ) {
    }
}
