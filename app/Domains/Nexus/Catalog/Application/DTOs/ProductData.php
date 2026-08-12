<?php

namespace App\Domains\Nexus\Catalog\Application\DTOs;

use App\Domains\Nexus\Catalog\Domain\Entities\Product;

final class ProductData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $businessId,
        public readonly string $nameFa,
        public readonly string $nameEn,
        public readonly int $priceAmount,
        public readonly string $priceCurrency,
        public readonly int $stockQuantity,
        public readonly ?array $attributes,
        public readonly string $verificationStatus,
    ) {
    }

    public static function fromEntity(Product $product): self
    {
        return new self(
            id: $product->id(),
            businessId: $product->businessId(),
            nameFa: $product->nameFa(),
            nameEn: $product->nameEn(),
            priceAmount: $product->price()->amount(),
            priceCurrency: $product->price()->currency(),
            stockQuantity: $product->stockQuantity(),
            attributes: $product->attributes(),
            verificationStatus: $product->verificationStatus()->value,
        );
    }

    /**
     * @return array{id: ?int, businessId: int, nameFa: string, nameEn: string, priceAmount: int, priceCurrency: string, stockQuantity: int, attributes: ?array, verificationStatus: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'businessId' => $this->businessId,
            'nameFa' => $this->nameFa,
            'nameEn' => $this->nameEn,
            'priceAmount' => $this->priceAmount,
            'priceCurrency' => $this->priceCurrency,
            'stockQuantity' => $this->stockQuantity,
            'attributes' => $this->attributes,
            'verificationStatus' => $this->verificationStatus,
        ];
    }
}
