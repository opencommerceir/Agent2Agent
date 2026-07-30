<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Product;
use App\Modules\Commerce\Domain\ValueObjects\ProductStatus;
use App\Modules\Commerce\Domain\ValueObjects\SKU;

/**
 * Contract owned by the Domain layer. Infrastructure provides the
 * implementation (Interfaces Over Tight Coupling). Every method takes
 * tenantId explicitly — never inferred from ambient/global state — so a
 * caller can never accidentally cross a tenant boundary by omission.
 */
interface ProductRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Product;

    public function findBySku(SKU $sku, int $tenantId): ?Product;

    public function skuExists(SKU $sku, int $tenantId): bool;

    /**
     * @return list<Product>
     */
    public function search(int $tenantId, ?string $query, ?ProductStatus $status, int $limit, int $offset): array;

    public function save(Product $product): Product;

    public function delete(int $id, int $tenantId): void;
}
