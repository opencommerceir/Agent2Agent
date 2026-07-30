<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\Entities\Role;

final class RoleData
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly array $permissions,
    ) {
    }

    public static function fromEntity(Role $role): self
    {
        return new self(
            id: $role->id(),
            tenantId: $role->tenantId(),
            name: $role->name(),
            slug: $role->slug(),
            description: $role->description(),
            permissions: array_map(fn ($key) => $key->value(), $role->permissions()),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'permissions' => $this->permissions,
        ];
    }
}
