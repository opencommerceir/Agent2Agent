<?php

namespace App\Domains\Nexus\PrivateMarketplace\Domain\Entities;

use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use DateTimeImmutable;

/**
 * Reuses Negotiation's own CatalogItemType/Money VOs directly rather than
 * copying them a fifth time — same reasoning Coalition (Phase 5/M3) already
 * established: a listing's specialPrice always heads straight into a real
 * Negotiation the moment a fellow member wants to buy it, so a separate
 * copy would just be converted straight back.
 */
final class PrivateMarketplaceListing
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $privateMarketplaceId,
        private readonly int $listingBusinessId,
        private readonly CatalogItemType $catalogItemType,
        private readonly int $catalogItemId,
        private readonly Money $specialPrice,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function add(
        int $privateMarketplaceId,
        int $listingBusinessId,
        CatalogItemType $catalogItemType,
        int $catalogItemId,
        Money $specialPrice,
    ): self {
        return new self(
            id: null,
            privateMarketplaceId: $privateMarketplaceId,
            listingBusinessId: $listingBusinessId,
            catalogItemType: $catalogItemType,
            catalogItemId: $catalogItemId,
            specialPrice: $specialPrice,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function privateMarketplaceId(): int
    {
        return $this->privateMarketplaceId;
    }

    public function listingBusinessId(): int
    {
        return $this->listingBusinessId;
    }

    public function catalogItemType(): CatalogItemType
    {
        return $this->catalogItemType;
    }

    public function catalogItemId(): int
    {
        return $this->catalogItemId;
    }

    public function specialPrice(): Money
    {
        return $this->specialPrice;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
