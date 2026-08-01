<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\Entities\Tenant;

/**
 * Structured data transfer for Tenant across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class TenantData
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $status,
        public readonly string $defaultLanguage,
    ) {
    }

    public static function fromEntity(Tenant $tenant): self
    {
        return new self(
            id: $tenant->id(),
            name: $tenant->name(),
            slug: $tenant->slug(),
            status: $tenant->status()->value,
            defaultLanguage: $tenant->defaultLanguage()->value,
        );
    }

    /**
     * @return array{id: ?int, name: string, slug: string, status: string, defaultLanguage: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'defaultLanguage' => $this->defaultLanguage,
        ];
    }
}
