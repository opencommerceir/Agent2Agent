<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\OrganizationMember;

/**
 * Listened to by RevokeRolesWhenMemberRemovedFromOrganization
 * (Application/Listeners) to cascade-revoke the member's Core roles.
 */
final class MemberRemovedFromOrganization
{
    public function __construct(
        public readonly OrganizationMember $member,
    ) {
    }
}
