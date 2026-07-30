<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\Entities\Organization;

final class OrganizationData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?int $ownerUserId,
        public readonly string $status,
    ) {
    }

    public static function fromEntity(Organization $organization): self
    {
        return new self(
            id: $organization->id(),
            tenantId: $organization->tenantId(),
            name: $organization->name(),
            slug: $organization->slug(),
            ownerUserId: $organization->ownerUserId(),
            status: $organization->status()->value,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'name' => $this->name,
            'slug' => $this->slug,
            'ownerUserId' => $this->ownerUserId,
            'status' => $this->status,
        ];
    }
}
