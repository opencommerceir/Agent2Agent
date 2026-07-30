<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\Category;

/**
 * Structured data transfer for Category across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class CategoryData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
    ) {
    }

    public static function fromEntity(Category $category): self
    {
        return new self(
            id: $category->id(),
            tenantId: $category->tenantId(),
            name: $category->name(),
            slug: $category->slug(),
            description: $category->description(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
        ];
    }
}
