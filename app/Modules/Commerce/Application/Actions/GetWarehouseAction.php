<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\WarehouseData;
use App\Modules\Commerce\Domain\Exceptions\WarehouseNotFoundException;
use App\Modules\Commerce\Domain\Repositories\WarehouseRepositoryInterface;

final class GetWarehouseAction
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
    ) {
    }

    public function execute(int $id, int $tenantId): WarehouseData
    {
        $warehouse = $this->warehouses->findById($id, $tenantId);

        if (! $warehouse) {
            throw new WarehouseNotFoundException("Warehouse [{$id}] does not exist.");
        }

        return WarehouseData::fromEntity($warehouse);
    }
}
