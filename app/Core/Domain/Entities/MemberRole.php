<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\ValueObjects\MemberType;
use DateTimeImmutable;

/**
 * The fact that a specific Role has been granted to a specific member
 * (polymorphic via MemberType). This is the pivot CheckPermissionAction
 * ultimately walks: member -> MemberRole -> Role -> Permission.
 */
final class MemberRole
{
    public function __construct(
        private readonly ?int $id,
        private readonly MemberType $memberType,
        private readonly int $memberId,
        private readonly int $roleId,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function assign(MemberType $memberType, int $memberId, int $roleId): self
    {
        return new self(
            id: null,
            memberType: $memberType,
            memberId: $memberId,
            roleId: $roleId,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function memberType(): MemberType
    {
        return $this->memberType;
    }

    public function memberId(): int
    {
        return $this->memberId;
    }

    public function roleId(): int
    {
        return $this->roleId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
