<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\ProductData;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;

final class GetProductAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function execute(int $id, int $tenantId): ProductData
    {
        $product = $this->products->findById($id, $tenantId);

        if (! $product) {
            throw new ProductNotFoundException("Product [{$id}] does not exist.");
        }

        return ProductData::fromEntity($product);
    }
}
