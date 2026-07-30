<?php

namespace App\Modules\Commerce\Domain\UCP;

/**
 * Normalized product shape every commerce source (Shopify, WooCommerce,
 * a custom ERP, ...) gets translated into by its Connector. A snapshot,
 * not a stored entity — the real identity of "this product" lives in the
 * external system; UCP never persists it (Connector Architecture,
 * architecture.md). priceAmount is the smallest currency unit (cents) to
 * avoid float rounding across currencies.
 */
final class UCPProduct
{
    /**
     * @param list<string> $categoryIds
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $sourceSystem,
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $priceAmount,
        public readonly string $priceCurrency,
        public readonly array $categoryIds = [],
        public readonly bool $isAvailable = true,
        public readonly array $attributes = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['externalId'],
            sourceSystem: $data['sourceSystem'],
            sku: $data['sku'],
            name: $data['name'],
            description: $data['description'] ?? null,
            priceAmount: $data['priceAmount'],
            priceCurrency: $data['priceCurrency'],
            categoryIds: $data['categoryIds'] ?? [],
            isAvailable: $data['isAvailable'] ?? true,
            attributes: $data['attributes'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'externalId' => $this->externalId,
            'sourceSystem' => $this->sourceSystem,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'priceAmount' => $this->priceAmount,
            'priceCurrency' => $this->priceCurrency,
            'categoryIds' => $this->categoryIds,
            'isAvailable' => $this->isAvailable,
            'attributes' => $this->attributes,
        ];
    }
}
