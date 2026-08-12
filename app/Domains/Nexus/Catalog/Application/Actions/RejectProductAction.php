<?php

namespace App\Domains\Nexus\Catalog\Application\Actions;

use App\Domains\Nexus\Catalog\Application\DTOs\ProductData;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use InvalidArgumentException;

/**
 * Admin-only (Dashboard, core `auth`/`admin` guard, never `business.auth`)
 * — the listing stays live (rejection isn't deletion, roadmap only asks
 * for a verification signal), it simply never earns the Verified badge.
 */
final class RejectProductAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function execute(int $productId): ProductData
    {
        $product = $this->products->findById($productId);

        if (! $product) {
            throw new InvalidArgumentException("Product [{$productId}] does not exist.");
        }

        $product->reject();

        return ProductData::fromEntity($this->products->save($product));
    }
}
