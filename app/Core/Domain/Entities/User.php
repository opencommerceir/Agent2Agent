<?php

namespace App\Core\Domain\Entities;

use DateTimeImmutable;

/**
 * Domain-level identity of a human user. Distinct from the framework's
 * Authenticatable model (App\Models\User), which handles session/auth
 * plumbing only. This entity carries the business identity concept.
 */
final class User
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private ?int $organizationId,
        private string $name,
        private string $email,
        private bool $active,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(int $tenantId, string $name, string $email, ?int $organizationId = null): self
    {
        return new self(
            id: null,
            tenantId: $tenantId,
            organizationId: $organizationId,
            name: $name,
            email: $email,
            active: true,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function attachToOrganization(int $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function organizationId(): ?int
    {
        return $this->organizationId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
