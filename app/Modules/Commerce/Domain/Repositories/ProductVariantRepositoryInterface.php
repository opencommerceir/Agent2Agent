<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\ProductVariant;
use App\Modules\Commerce\Domain\ValueObjects\VariantSKU;

interface ProductVariantRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?ProductVariant;

    public function skuExists(VariantSKU $sku, int $tenantId): bool;

    /**
     * @param array<string, string> $attributes exact combination to match, e.g. ['Color' => 'Red', 'Size' => 'L']
     */
    public function findByProductAndAttributes(int $productId, int $tenantId, array $attributes): ?ProductVariant;

    /**
     * @return list<ProductVariant>
     */
    public function listByProduct(int $productId, int $tenantId): array;

    public function save(ProductVariant $variant): ProductVariant;

    public function delete(int $id, int $tenantId): void;
}
