<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Inventory;

interface InventoryRepositoryInterface
{
    public function findByProduct(int $productId, int $tenantId): ?Inventory;

    /**
     * Same lookup as findByProduct(), but takes a row-level lock
     * (`SELECT ... FOR UPDATE`) so a concurrent reservation against the
     * same product serializes instead of racing. Only meaningful inside
     * an active DB::transaction() — used by AddToCartAction to close the
     * check-then-act gap between reading available() and writing the new
     * reservation, which previously let two concurrent Agents each pass
     * the availability check before either had committed, over-reserving
     * stock beyond quantityOnHand.
     */
    public function findByProductForUpdate(int $productId, int $tenantId): ?Inventory;

    public function save(Inventory $inventory): Inventory;
}
