<?php

namespace App\Modules\Commerce\Domain\Entities;

use DateTimeImmutable;

/**
 * A tenant-scoped grouping of Products. Deliberately minimal in this
 * phase — no parent/child hierarchy, no ordering — since neither was
 * requested and both are easy to add later without breaking this shape.
 */
final class Category
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private string $name,
        private readonly string $slug,
        private ?string $description,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(int $tenantId, string $name, string $slug, ?string $description = null): self
    {
        return new self(
            id: null,
            tenantId: $tenantId,
            name: $name,
            slug: $slug,
            description: $description,
            createdAt: new DateTimeImmutable(),
        );
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

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
