<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Domain\Exceptions\WarehouseNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\WarehouseRepositoryInterface;

/**
 * "How much of one Product (or one specific ProductVariant) is physically
 * on hand at one specific Warehouse" — a read-only lookup, the same
 * "no record = zero" convention CheckInventoryAction already establishes
 * (a Warehouse simply never having stocked this Product yet is not an
 * error). There is no companion "set warehouse stock" capability this
 * stage — initial per-warehouse provisioning goes through
 * `Inventory::setQuantityOnHand()` directly (already exists, §7.21),
 * the same "built the mechanism, one real Action-level entry point wasn't
 * requested yet" gap this codebase has carried before (HANDOFF §6/§8.2).
 */
final class GetWarehouseStockAction
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    /**
     * @return array{warehouseId: int, productId: int, variantId: ?int, quantityOnHand: int, quantityReserved: int, quantityAvailable: int}
     */
    public function execute(int $tenantId, int $warehouseId, int $productId, ?int $variantId = null): array
    {
        if (! $this->warehouses->findById($warehouseId, $tenantId)) {
            throw new WarehouseNotFoundException("Warehouse [{$warehouseId}] does not exist.");
        }

        $inventory = $this->inventories->findByProduct($productId, $tenantId, $variantId, $warehouseId);

        return [
            'warehouseId' => $warehouseId,
            'productId' => $productId,
            'variantId' => $variantId,
            'quantityOnHand' => $inventory?->quantityOnHand() ?? 0,
            'quantityReserved' => $inventory?->quantityReserved() ?? 0,
            'quantityAvailable' => $inventory?->available() ?? 0,
        ];
    }
}
