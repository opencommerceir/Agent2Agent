<?php

namespace App\Domains\Nexus\Catalog\Application\Actions;

use App\Domains\Nexus\Catalog\Application\DTOs\ProductData;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use InvalidArgumentException;

/**
 * Admin-only (Dashboard, core `auth`/`admin` guard, never `business.auth`)
 * — Phase 6/M5's listing-level trust signal, mirrors VerifyBusinessAction's
 * shape exactly (no acting-business parameter, no event dispatch — no
 * other domain reacts to a single listing being verified the way Agent
 * auto-creation reacts to BusinessWasVerified).
 */
final class VerifyProductAction
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

        $product->verify();

        return ProductData::fromEntity($this->products->save($product));
    }
}
