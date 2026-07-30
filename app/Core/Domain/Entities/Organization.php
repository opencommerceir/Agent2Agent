<?php

namespace App\Core\Domain\Entities;

use DateTimeImmutable;

/**
 * Represents the business account operating inside a Tenant.
 * Every Organization belongs to exactly one Tenant (tenant_id required).
 */
final class Organization
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private string $name,
        private string $slug,
        private ?int $ownerUserId,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(int $tenantId, string $name, string $slug, ?int $ownerUserId = null): self
    {
        return new self(
            id: null,
            tenantId: $tenantId,
            name: $name,
            slug: $slug,
            ownerUserId: $ownerUserId,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function assignOwner(int $userId): void
    {
        $this->ownerUserId = $userId;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function ownerUserId(): ?int
    {
        return $this->ownerUserId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
