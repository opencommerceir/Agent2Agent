<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\DTOs;

use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplaceListing;

final class PrivateMarketplaceListingData
{
    public function __construct(
        public readonly int $id,
        public readonly int $privateMarketplaceId,
        public readonly int $listingBusinessId,
        public readonly string $listingBusinessNameEn,
        public readonly string $catalogItemType,
        public readonly int $catalogItemId,
        public readonly int $specialPriceAmount,
        public readonly string $specialPriceCurrency,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(PrivateMarketplaceListing $listing, string $listingBusinessNameEn): self
    {
        return new self(
            id: $listing->id(),
            privateMarketplaceId: $listing->privateMarketplaceId(),
            listingBusinessId: $listing->listingBusinessId(),
            listingBusinessNameEn: $listingBusinessNameEn,
            catalogItemType: $listing->catalogItemType()->value,
            catalogItemId: $listing->catalogItemId(),
            specialPriceAmount: $listing->specialPrice()->amount(),
            specialPriceCurrency: $listing->specialPrice()->currency(),
            createdAt: $listing->createdAt()->format(DATE_ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'privateMarketplaceId' => $this->privateMarketplaceId,
            'listingBusinessId' => $this->listingBusinessId,
            'listingBusinessNameEn' => $this->listingBusinessNameEn,
            'catalogItemType' => $this->catalogItemType,
            'catalogItemId' => $this->catalogItemId,
            'specialPriceAmount' => $this->specialPriceAmount,
            'specialPriceCurrency' => $this->specialPriceCurrency,
            'createdAt' => $this->createdAt,
        ];
    }
}
