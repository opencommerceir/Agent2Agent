<?php

namespace App\Domains\Nexus\Catalog\Application\Actions;

use App\Domains\Nexus\Catalog\Application\DTOs\ProductData;
use App\Domains\Nexus\Catalog\Domain\Entities\Product;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\Money;

final class AddProductAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function execute(
        int $businessId,
        string $nameFa,
        string $nameEn,
        int $priceAmount,
        string $priceCurrency,
        int $stockQuantity = 0,
        ?array $attributes = null,
    ): ProductData {
        $product = Product::add(
            businessId: $businessId,
            nameFa: $nameFa,
            nameEn: $nameEn,
            price: Money::fromAmount($priceAmount, $priceCurrency),
            stockQuantity: $stockQuantity,
            attributes: $attributes,
        );
        $product = $this->products->save($product);

        return ProductData::fromEntity($product);
    }
}
