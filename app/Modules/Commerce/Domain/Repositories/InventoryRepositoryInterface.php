<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Inventory;

interface InventoryRepositoryInterface
{
    /**
     * $variantId (Phase 5, Stage 1 — Product Variants, §7.21) is an
     * optional trailing param, the same "widen, don't duplicate" shape
     * every other cross-stage Inventory extension has used — null looks
     * up the parent Product's own row (unchanged behavior for every
     * existing caller that doesn't pass it), a real value looks up that
     * specific ProductVariant's own row instead.
     *
     * $warehouseId (Phase 5, Stage 2 — Multi-warehouse Inventory, §7.22)
     * is a second optional trailing param, the identical shape — null
     * looks up the tenant's own default (non-warehouse-scoped) row,
     * unchanged for every existing caller (AddToCartAction/
     * PlaceOrderAction/CheckInventoryAction never pass it); a real value
     * looks up that specific Warehouse's own row instead.
     */
    public function findByProduct(int $productId, int $tenantId, ?int $variantId = null, ?int $warehouseId = null): ?Inventory;

    /**
     * Same lookup as findByProduct(), but takes a row-level lock
     * (`SELECT ... FOR UPDATE`) so a concurrent reservation against the
     * same product (or variant) serializes instead of racing. Only
     * meaningful inside an active DB::transaction() — used by
     * AddToCartAction to close the check-then-act gap between reading
     * available() and writing the new reservation, which previously let
     * two concurrent Agents each pass the availability check before
     * either had committed, over-reserving stock beyond quantityOnHand.
     * ApproveWarehouseTransferAction (§7.22) uses the same lock for the
     * identical reason when reserving stock at the source Warehouse.
     */
    public function findByProductForUpdate(int $productId, int $tenantId, ?int $variantId = null, ?int $warehouseId = null): ?Inventory;

    /**
     * Added for Analytics' own Low Stock Products KPI (Phase 4 Stage 6,
     * §7.18) — nothing before this needed to list Inventory across every
     * Product for a tenant, only single-Product lookups existed.
     * "Low stock" means available() (on-hand minus reserved) below
     * $threshold — the same available-stock definition
     * `CheckInventoryAction` already uses, not raw on-hand alone.
     *
     * @return list<Inventory>
     */
    public function listLowStock(int $tenantId, int $threshold): array;

    /**
     * Added for Multi-warehouse Inventory (Phase 5, Stage 2, §7.22) —
     * every Inventory row for one Product (or one ProductVariant) across
     * every Warehouse, including the default (warehouse_id null) row if
     * one exists. GetWarehouseStockAction and FindNearestWarehouseAction
     * both need "which Warehouses carry this Product and how much,"
     * which no single-row lookup above can answer.
     *
     * @return list<Inventory>
     */
    public function listByProduct(int $productId, int $tenantId, ?int $variantId = null): array;

    public function save(Inventory $inventory): Inventory;
}
