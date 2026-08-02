<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\ProductVariantData;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Entities\ProductVariant;
use App\Modules\Commerce\Domain\Events\VariantWasCreated;
use App\Modules\Commerce\Domain\Exceptions\DuplicateVariantException;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductVariantRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\VariantSKU;
use Illuminate\Support\Facades\Event;

/**
 * `attributes` (name => value, e.g. `['Color' => 'Red', 'Size' => 'L']`)
 * is taken at face value for the SKU/JSON snapshot, the same deliberate
 * looseness Product's own `attributes` bag already has (no registry-level
 * check that each name/value matches a real VariantAttribute/
 * VariantAttributeValue row — see ProductVariant's own migration
 * docblock, §7.21, and Shipping's `weight_grams` precedent, §8.34).
 * GenerateVariantCombinationsAction is the registry-driven path; this one
 * is the direct, free-form path, mirroring CreateProductAction's own
 * free-form `attributes` input.
 */
final class CreateProductVariantAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductVariantRepositoryInterface $variants,
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    /**
     * @param array<string, string> $attributes
     */
    public function execute(
        int $tenantId,
        int $productId,
        array $attributes,
        int $priceAmount,
        string $priceCurrency,
        ?string $imageUrl = null,
        int $initialStock = 0,
    ): ProductVariantData {
        $product = $this->products->findById($productId, $tenantId);

        if (! $product) {
            throw new ProductNotFoundException("Product [{$productId}] does not exist.");
        }

        if ($this->variants->findByProductAndAttributes($productId, $tenantId, $attributes) !== null) {
            throw new DuplicateVariantException(
                'A variant with this exact attribute combination already exists for this product.'
            );
        }

        $sku = VariantSKU::generate($product->sku(), array_values($attributes));

        if ($this->variants->skuExists($sku, $tenantId)) {
            throw new DuplicateVariantException("Variant SKU [{$sku->value()}] already exists for this tenant.");
        }

        $variant = ProductVariant::create(
            tenantId: $tenantId,
            productId: $productId,
            sku: $sku,
            price: Money::fromAmount($priceAmount, $priceCurrency),
            attributes: $attributes,
            imageUrl: $imageUrl,
        );
        $variant = $this->variants->save($variant);

        $inventory = Inventory::stock($tenantId, $productId, $initialStock, $variant->id());
        $inventory = $this->inventories->save($inventory);

        if (! $product->isParent()) {
            $product->markAsParent();
            $this->products->save($product);
        }

        Event::dispatch(new VariantWasCreated($variant));

        return ProductVariantData::fromEntity($variant, $inventory);
    }
}
