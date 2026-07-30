<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\OrganizationMember;

final class MemberAddedToOrganization
{
    public function __construct(
        public readonly OrganizationMember $member,
    ) {
    }
}
