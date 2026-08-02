<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Entities\ProductVariant;

/**
 * Structured data transfer for ProductVariant across layers. quantityOnHand/
 * quantityAvailable are nullable and populated only when the calling
 * Action also has the variant's own Inventory row at hand (fromEntity()'s
 * optional second argument) — this DTO itself never fetches anything,
 * same as every other DTO in this codebase (DTO Conventions).
 */
final class ProductVariantData
{
    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $productId,
        public readonly string $sku,
        public readonly int $priceAmount,
        public readonly string $priceCurrency,
        public readonly array $attributes,
        public readonly ?string $imageUrl,
        public readonly bool $isActive,
        public readonly ?int $quantityOnHand = null,
        public readonly ?int $quantityAvailable = null,
    ) {
    }

    public static function fromEntity(ProductVariant $variant, ?Inventory $inventory = null): self
    {
        return new self(
            id: $variant->id(),
            tenantId: $variant->tenantId(),
            productId: $variant->productId(),
            sku: $variant->sku()->value(),
            priceAmount: $variant->price()->amount(),
            priceCurrency: $variant->price()->currency(),
            attributes: $variant->attributes(),
            imageUrl: $variant->imageUrl(),
            isActive: $variant->isActive(),
            quantityOnHand: $inventory?->quantityOnHand(),
            quantityAvailable: $inventory?->available(),
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
            'productId' => $this->productId,
            'sku' => $this->sku,
            'priceAmount' => $this->priceAmount,
            'priceCurrency' => $this->priceCurrency,
            'attributes' => $this->attributes,
            'imageUrl' => $this->imageUrl,
            'isActive' => $this->isActive,
            'quantityOnHand' => $this->quantityOnHand,
            'quantityAvailable' => $this->quantityAvailable,
        ];
    }
}
