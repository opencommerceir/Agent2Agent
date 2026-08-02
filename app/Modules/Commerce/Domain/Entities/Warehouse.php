<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\WarehouseCode;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;
use DateTimeImmutable;

/**
 * A tenant-owned physical stock location. `code` is readonly — a
 * Warehouse's code is its business identity, the same "not updatable
 * after creation" rule Product's own SKU/Category's own slug already
 * have. `name`/`location`/`isActive` are the only mutable fields.
 */
final class Warehouse
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly WarehouseCode $code,
        private string $name,
        private WarehouseLocation $location,
        private bool $isActive,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        int $tenantId,
        WarehouseCode $code,
        string $name,
        WarehouseLocation $location,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: null,
            tenantId: $tenantId,
            code: $code,
            name: $name,
            location: $location,
            isActive: true,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function update(string $name, WarehouseLocation $location): void
    {
        $this->name = $name;
        $this->location = $location;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function code(): WarehouseCode
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function location(): WarehouseLocation
    {
        return $this->location;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
