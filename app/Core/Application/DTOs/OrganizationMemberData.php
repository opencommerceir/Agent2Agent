<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\Entities\OrganizationMember;

final class OrganizationMemberData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $organizationId,
        public readonly string $memberType,
        public readonly int $memberId,
        public readonly string $roleInOrg,
    ) {
    }

    public static function fromEntity(OrganizationMember $member): self
    {
        return new self(
            id: $member->id(),
            tenantId: $member->tenantId(),
            organizationId: $member->organizationId(),
            memberType: $member->memberType()->value,
            memberId: $member->memberId(),
            roleInOrg: $member->roleInOrg()->value,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'organizationId' => $this->organizationId,
            'memberType' => $this->memberType,
            'memberId' => $this->memberId,
            'roleInOrg' => $this->roleInOrg,
        ];
    }
}
