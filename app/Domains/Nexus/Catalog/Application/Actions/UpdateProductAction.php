<?php

namespace App\Domains\Nexus\Catalog\Application\Actions;

use App\Domains\Nexus\Catalog\Application\DTOs\ProductData;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\Money;
use InvalidArgumentException;

/**
 * The roadmap names a single "UpdateCatalog" action; implemented here as
 * UpdateProductAction/UpdateServiceAction (one per entity type) instead —
 * Product and Service have different field shapes (stock_quantity vs.
 * duration_minutes), and every other Action in this codebase is one
 * entity type per Action (Application Layer Rules: "one action = one
 * responsibility"). SearchCatalogAction is the one Action that genuinely
 * spans both types, since a catalog search has to.
 */
final class UpdateProductAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function execute(
        int $productId,
        string $nameFa,
        string $nameEn,
        int $priceAmount,
        string $priceCurrency,
        int $stockQuantity,
        ?array $attributes,
    ): ProductData {
        $product = $this->products->findById($productId);

        if (! $product) {
            throw new InvalidArgumentException("Product [{$productId}] does not exist.");
        }

        $product->update($nameFa, $nameEn, Money::fromAmount($priceAmount, $priceCurrency), $stockQuantity, $attributes);
        $product = $this->products->save($product);

        return ProductData::fromEntity($product);
    }
}
