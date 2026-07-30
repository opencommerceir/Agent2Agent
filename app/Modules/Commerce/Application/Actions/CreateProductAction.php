<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\ProductData;
use App\Modules\Commerce\Domain\Entities\Product;
use App\Modules\Commerce\Domain\Events\ProductWasCreated;
use App\Modules\Commerce\Domain\Exceptions\DuplicateSKUException;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\ProductStatus;
use App\Modules\Commerce\Domain\ValueObjects\SKU;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * One Action = one business operation: create a Product and dispatch the
 * corresponding domain event. SKU uniqueness is enforced per-tenant, not
 * globally (Multi-Tenancy default) — two different tenants may both use
 * "SKU-001" without conflict.
 */
final class CreateProductAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function execute(
        int $tenantId,
        string $name,
        string $sku,
        int $priceAmount,
        string $priceCurrency,
        ?int $categoryId = null,
        ?string $description = null,
        string $status = 'draft',
        array $attributes = [],
    ): ProductData {
        $skuValue = new SKU($sku); // throws InvalidSKUException on bad format

        if ($this->products->skuExists($skuValue, $tenantId)) {
            throw new DuplicateSKUException("SKU [{$skuValue}] already exists for this tenant.");
        }

        $product = Product::create(
            tenantId: $tenantId,
            categoryId: $categoryId,
            name: $name,
            slug: Str::slug($name),
            description: $description,
            sku: $skuValue,
            price: Money::fromAmount($priceAmount, $priceCurrency),
            status: ProductStatus::from($status),
            attributes: $attributes,
        );

        $product = $this->products->save($product);

        Event::dispatch(new ProductWasCreated($product));

        return ProductData::fromEntity($product);
    }
}
