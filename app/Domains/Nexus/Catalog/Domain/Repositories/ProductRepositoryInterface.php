<?php

namespace App\Domains\Nexus\Catalog\Domain\Repositories;

use App\Domains\Nexus\Catalog\Domain\Entities\Product;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    /**
     * @return list<Product>
     */
    public function findByBusinessId(int $businessId): array;

    /**
     * @return list<Product>
     */
    public function search(int $businessId, string $query): array;

    public function save(Product $product): Product;
}
