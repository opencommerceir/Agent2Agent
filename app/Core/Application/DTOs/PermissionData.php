<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\Entities\Permission;

final class PermissionData
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $key,
        public readonly ?string $description,
    ) {
    }

    public static function fromEntity(Permission $permission): self
    {
        return new self(
            id: $permission->id(),
            key: $permission->key()->value(),
            description: $permission->description(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'description' => $this->description,
        ];
    }
}
