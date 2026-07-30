<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\MemberRole;

final class RoleAssignedToMember
{
    public function __construct(
        public readonly MemberRole $memberRole,
    ) {
    }
}
