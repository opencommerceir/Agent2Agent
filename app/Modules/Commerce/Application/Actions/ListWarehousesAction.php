<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\WarehouseData;
use App\Modules\Commerce\Domain\Repositories\WarehouseRepositoryInterface;

final class ListWarehousesAction
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
    ) {
    }

    /**
     * @return list<WarehouseData>
     */
    public function execute(int $tenantId, ?bool $isActive = null): array
    {
        return array_map(
            fn ($warehouse) => WarehouseData::fromEntity($warehouse),
            $this->warehouses->listByTenant($tenantId, $isActive),
        );
    }
}
