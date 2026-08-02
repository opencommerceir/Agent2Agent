<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\WarehouseData;
use App\Modules\Commerce\Domain\Exceptions\WarehouseNotFoundException;
use App\Modules\Commerce\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;

/**
 * `code` is not updatable — a Warehouse's code is its business identity,
 * the same "not updatable after creation" rule Product's own SKU has
 * (Warehouse entity's own docblock). `isActive` is toggled through this
 * same Action (activate/deactivate) rather than a separate capability —
 * mirrors how `commerce.variant.update` folds `is_active` into its own
 * update input instead of a dedicated toggle capability.
 */
final class UpdateWarehouseAction
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
    ) {
    }

    public function execute(
        int $id,
        int $tenantId,
        string $name,
        float $latitude,
        float $longitude,
        string $address,
        bool $isActive = true,
    ): WarehouseData {
        $warehouse = $this->warehouses->findById($id, $tenantId);

        if (! $warehouse) {
            throw new WarehouseNotFoundException("Warehouse [{$id}] does not exist.");
        }

        $warehouse->update($name, new WarehouseLocation($latitude, $longitude, $address));

        if ($isActive) {
            $warehouse->activate();
        } else {
            $warehouse->deactivate();
        }

        $warehouse = $this->warehouses->save($warehouse);

        return WarehouseData::fromEntity($warehouse);
    }
}
