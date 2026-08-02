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
     */
    public function findByProduct(int $productId, int $tenantId, ?int $variantId = null): ?Inventory;

    /**
     * Same lookup as findByProduct(), but takes a row-level lock
     * (`SELECT ... FOR UPDATE`) so a concurrent reservation against the
     * same product (or variant) serializes instead of racing. Only
     * meaningful inside an active DB::transaction() — used by
     * AddToCartAction to close the check-then-act gap between reading
     * available() and writing the new reservation, which previously let
     * two concurrent Agents each pass the availability check before
     * either had committed, over-reserving stock beyond quantityOnHand.
     */
    public function findByProductForUpdate(int $productId, int $tenantId, ?int $variantId = null): ?Inventory;

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

    public function save(Inventory $inventory): Inventory;
}
