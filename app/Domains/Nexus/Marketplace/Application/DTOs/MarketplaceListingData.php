<?php

namespace App\Domains\Nexus\Marketplace\Application\DTOs;

/**
 * A single Business surfaced by Marketplace discovery, together with the
 * catalog items that matched. Represents data only — no business logic
 * (DTO Conventions).
 */
final class MarketplaceListingData
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $nameFa,
        public readonly string $nameEn,
        public readonly string $industry,
        public readonly array $products,
        public readonly array $services,
    ) {
    }

    /**
     * @return array{businessId: int, nameFa: string, nameEn: string, industry: string, products: array, services: array}
     */
    public function toArray(): array
    {
        return [
            'businessId' => $this->businessId,
            'nameFa' => $this->nameFa,
            'nameEn' => $this->nameEn,
            'industry' => $this->industry,
            'products' => $this->products,
            'services' => $this->services,
        ];
    }
}
