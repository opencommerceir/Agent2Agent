<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\ValueObjects\PermissionKey;
use DateTimeImmutable;

/**
 * A single grantable capability key (e.g. "commerce.products.read").
 * Deliberately global — not tenant-scoped. Permissions are platform
 * vocabulary defined by domain modules through the Capability Registry;
 * every tenant references the same key, they don't each own a private
 * copy of it. Roles (tenant-scoped) are what bundle these into
 * per-tenant, per-organization access bundles.
 */
final class Permission
{
    public function __construct(
        private readonly ?int $id,
        private readonly PermissionKey $key,
        private ?string $description,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(PermissionKey $key, ?string $description = null): self
    {
        return new self(
            id: null,
            key: $key,
            description: $description,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function key(): PermissionKey
    {
        return $this->key;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
