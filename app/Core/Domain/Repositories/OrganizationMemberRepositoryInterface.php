<?php

namespace App\Core\Domain\Repositories;

use App\Core\Domain\Entities\OrganizationMember;
use App\Core\Domain\ValueObjects\MemberType;

interface OrganizationMemberRepositoryInterface
{
    public function findMembership(int $organizationId, MemberType $memberType, int $memberId): ?OrganizationMember;

    public function save(OrganizationMember $member): OrganizationMember;

    public function delete(OrganizationMember $member): void;
}
