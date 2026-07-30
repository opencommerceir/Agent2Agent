<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\ProductData;
use App\Modules\Commerce\Domain\Events\ProductWasUpdated;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\ProductStatus;
use Illuminate\Support\Facades\Event;

/**
 * SKU is deliberately not updatable here — it is the Product's business
 * identity (Domain\Entities\Product docblock); changing it would need a
 * distinct, more deliberate operation than a generic field update.
 */
final class UpdateProductAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function execute(
        int $id,
        int $tenantId,
        string $name,
        ?string $description,
        int $priceAmount,
        string $priceCurrency,
        string $status,
        array $attributes = [],
        ?int $categoryId = null,
    ): ProductData {
        $product = $this->products->findById($id, $tenantId);

        if (! $product) {
            throw new ProductNotFoundException("Product [{$id}] does not exist.");
        }

        $product->update(
            categoryId: $categoryId,
            name: $name,
            description: $description,
            price: Money::fromAmount($priceAmount, $priceCurrency),
            status: ProductStatus::from($status),
            attributes: $attributes,
        );

        $product = $this->products->save($product);

        Event::dispatch(new ProductWasUpdated($product));

        return ProductData::fromEntity($product);
    }
}
