<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\Product;

/**
 * Structured data transfer for Product across layers.
 * Represents data only — no business logic (DTO Conventions). Price is
 * exposed as the same two fields (amount in cents + currency) the
 * `products` table and Money Value Object use, rather than a formatted
 * string, so callers decide their own formatting.
 */
final class ProductData
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly ?int $categoryId,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly string $sku,
        public readonly int $priceAmount,
        public readonly string $priceCurrency,
        public readonly string $status,
        public readonly array $attributes,
    ) {
    }

    public static function fromEntity(Product $product): self
    {
        return new self(
            id: $product->id(),
            tenantId: $product->tenantId(),
            categoryId: $product->categoryId(),
            name: $product->name(),
            slug: $product->slug(),
            description: $product->description(),
            sku: $product->sku()->value(),
            priceAmount: $product->price()->amount(),
            priceCurrency: $product->price()->currency(),
            status: $product->status()->value,
            attributes: $product->attributes(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'categoryId' => $this->categoryId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sku' => $this->sku,
            'priceAmount' => $this->priceAmount,
            'priceCurrency' => $this->priceCurrency,
            'status' => $this->status,
            'attributes' => $this->attributes,
        ];
    }
}
