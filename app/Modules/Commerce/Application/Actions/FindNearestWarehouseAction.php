<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\WarehouseData;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Commerce\Domain\Services\NearestWarehouseFinder;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;

/**
 * A pure preview, no side effects (Phase 5, Stage 2 — Multi-warehouse
 * Inventory, §7.22) — the data-fetching counterpart to
 * NearestWarehouseFinder's pure combination logic: this Action is
 * responsible for pulling active Warehouses and this Product's Inventory
 * rows from their Repositories and assembling the `$candidates` array
 * NearestWarehouseFinder itself is deliberately forbidden from building on
 * its own. Reused directly by Shipping's own CalculateShippingRateAction
 * the same way Analytics' CalculateKPIAction reuses Reporting's Query
 * Builders — a plain, concrete, container-autowired class, no Interface.
 */
final class FindNearestWarehouseAction
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly InventoryRepositoryInterface $inventories,
        private readonly NearestWarehouseFinder $finder,
    ) {
    }

    public function execute(
        int $tenantId,
        int $productId,
        float $customerLatitude,
        float $customerLongitude,
        int $requiredQuantity,
        ?int $variantId = null,
    ): ?WarehouseData {
        $activeWarehouses = $this->warehouses->listByTenant($tenantId, isActive: true);
        $inventoryRows = $this->inventories->listByProduct($productId, $tenantId, $variantId);

        $availableByWarehouseId = [];

        foreach ($inventoryRows as $inventory) {
            if ($inventory->warehouseId() !== null) {
                $availableByWarehouseId[$inventory->warehouseId()] = $inventory->available();
            }
        }

        $candidates = array_map(
            fn ($warehouse) => [
                'warehouse' => $warehouse,
                'availableQuantity' => $availableByWarehouseId[$warehouse->id()] ?? 0,
            ],
            $activeWarehouses,
        );

        // WarehouseLocation requires a non-empty address; a placeholder is
        // used here since the address is never read for distance math.
        $customerLocation = new WarehouseLocation($customerLatitude, $customerLongitude, 'Customer Location');

        $nearest = $this->finder->find($candidates, $customerLocation, $requiredQuantity);

        return $nearest ? WarehouseData::fromEntity($nearest) : null;
    }
}
