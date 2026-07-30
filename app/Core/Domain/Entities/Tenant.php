<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\ValueObjects\TenantStatus;
use DateTimeImmutable;

/**
 * Tenant is the isolation boundary of the platform (Decision 011).
 * It is the only Core entity that does not carry a tenant_id.
 */
final class Tenant
{
    public function __construct(
        private readonly ?int $id,
        private string $name,
        private string $slug,
        private TenantStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(string $name, string $slug): self
    {
        return new self(
            id: null,
            name: $name,
            slug: $slug,
            status: TenantStatus::Pending,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function activate(): void
    {
        $this->status = TenantStatus::Active;
    }

    public function suspend(): void
    {
        $this->status = TenantStatus::Suspended;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function status(): TenantStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }
}
