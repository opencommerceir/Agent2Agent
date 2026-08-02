<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\Warehouse;

final class WarehouseData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $code,
        public readonly string $name,
        public readonly WarehouseLocationData $location,
        public readonly bool $isActive,
    ) {
    }

    public static function fromEntity(Warehouse $warehouse): self
    {
        return new self(
            id: $warehouse->id(),
            tenantId: $warehouse->tenantId(),
            code: $warehouse->code()->value(),
            name: $warehouse->name(),
            location: WarehouseLocationData::fromValueObject($warehouse->location()),
            isActive: $warehouse->isActive(),
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
            'code' => $this->code,
            'name' => $this->name,
            'location' => $this->location->toArray(),
            'isActive' => $this->isActive,
        ];
    }
}
