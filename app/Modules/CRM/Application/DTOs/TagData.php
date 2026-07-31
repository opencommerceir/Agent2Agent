<?php

namespace App\Modules\CRM\Application\DTOs;

use App\Modules\CRM\Domain\Entities\Tag;

/**
 * Structured data transfer for Tag across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class TagData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly ?string $color,
    ) {
    }

    public static function fromEntity(Tag $tag): self
    {
        return new self(
            id: $tag->id(),
            tenantId: $tag->tenantId(),
            name: $tag->name()->value(),
            color: $tag->color(),
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
            'color' => $this->color,
        ];
    }
}
