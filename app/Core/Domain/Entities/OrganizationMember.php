<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\OrganizationMemberRole;
use DateTimeImmutable;

/**
 * Membership fact: this User or Agent (polymorphic via MemberType) belongs
 * to this Organization. Deliberately separate from Role/Permission — this
 * is organizational governance ("who's in this org and at what standing"),
 * not capability authorization.
 */
final class OrganizationMember
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $organizationId,
        private readonly MemberType $memberType,
        private readonly int $memberId,
        private OrganizationMemberRole $roleInOrg,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function add(
        int $tenantId,
        int $organizationId,
        MemberType $memberType,
        int $memberId,
        OrganizationMemberRole $roleInOrg = OrganizationMemberRole::Member,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            organizationId: $organizationId,
            memberType: $memberType,
            memberId: $memberId,
            roleInOrg: $roleInOrg,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function changeRoleInOrg(OrganizationMemberRole $role): void
    {
        $this->roleInOrg = $role;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function organizationId(): int
    {
        return $this->organizationId;
    }

    public function memberType(): MemberType
    {
        return $this->memberType;
    }

    public function memberId(): int
    {
        return $this->memberId;
    }

    public function roleInOrg(): OrganizationMemberRole
    {
        return $this->roleInOrg;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
