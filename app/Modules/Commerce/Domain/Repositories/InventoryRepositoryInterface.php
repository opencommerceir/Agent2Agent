<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Inventory;

interface InventoryRepositoryInterface
{
    public function findByProduct(int $productId, int $tenantId): ?Inventory;

    public function save(Inventory $inventory): Inventory;
}
