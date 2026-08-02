<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\ProductVariantData;
use App\Modules\Commerce\Domain\Entities\ProductVariant;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductVariantRepositoryInterface;

/**
 * One Inventory lookup per variant returned — a bounded, small-N per-row
 * Repository lookup, the same precedent Reporting's own
 * GenerateTopProductsReportAction/GenerateTopCustomersReportAction
 * already established for resolving a display name per already-limited
 * row (§7.11), not a batch method added to InventoryRepositoryInterface
 * for what a Product's own variant count realistically bounds anyway.
 */
final class ListProductVariantsAction
{
    public function __construct(
        private readonly ProductVariantRepositoryInterface $variants,
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    /**
     * @return list<ProductVariantData>
     */
    public function execute(int $productId, int $tenantId): array
    {
        return array_map(
            fn (ProductVariant $variant) => ProductVariantData::fromEntity(
                $variant,
                $this->inventories->findByProduct($productId, $tenantId, $variant->id()),
            ),
            $this->variants->listByProduct($productId, $tenantId),
        );
    }
}
