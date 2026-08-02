<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\ProductVariantData;
use App\Modules\Commerce\Domain\Exceptions\VariantNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductVariantRepositoryInterface;

final class GetProductVariantAction
{
    public function __construct(
        private readonly ProductVariantRepositoryInterface $variants,
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    public function execute(int $id, int $tenantId): ProductVariantData
    {
        $variant = $this->variants->findById($id, $tenantId);

        if (! $variant) {
            throw new VariantNotFoundException("Variant [{$id}] does not exist.");
        }

        $inventory = $this->inventories->findByProduct($variant->productId(), $tenantId, $variant->id());

        return ProductVariantData::fromEntity($variant, $inventory);
    }
}
