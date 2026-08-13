<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\Actions;

use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\PrivateMarketplace\Application\DTOs\PrivateMarketplaceListingData;
use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplaceListing;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceListingRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceMemberRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\ValueObjects\PrivateMarketplaceMemberStatus;
use InvalidArgumentException;

/**
 * Member-only (owner or Active member) — a small anti-spam CostGate charge,
 * same reasoning nexus.invite.send already established (an internal
 * membership doesn't make posting free of abuse potential, just cheaper
 * than an external send).
 */
final class AddListingAction
{
    public function __construct(
        private readonly PrivateMarketplaceRepositoryInterface $marketplaces,
        private readonly PrivateMarketplaceMemberRepositoryInterface $members,
        private readonly PrivateMarketplaceListingRepositoryInterface $listings,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    public function execute(
        int $marketplaceId,
        int $listingBusinessId,
        CatalogItemType $catalogItemType,
        int $catalogItemId,
        int $specialPriceAmount,
        string $specialPriceCurrency,
    ): PrivateMarketplaceListingData {
        $marketplace = $this->marketplaces->findById($marketplaceId);

        if (! $marketplace) {
            throw new InvalidArgumentException("Private Marketplace [{$marketplaceId}] does not exist.");
        }

        $this->assertMembership($marketplace->ownerBusinessId(), $marketplaceId, $listingBusinessId);

        $this->costGate->execute($listingBusinessId, 'nexus.private_marketplace.list_listing');

        $listing = $this->listings->save(PrivateMarketplaceListing::add(
            privateMarketplaceId: $marketplaceId,
            listingBusinessId: $listingBusinessId,
            catalogItemType: $catalogItemType,
            catalogItemId: $catalogItemId,
            specialPrice: Money::fromAmount($specialPriceAmount, $specialPriceCurrency),
        ));

        return PrivateMarketplaceListingData::fromEntity($listing, '');
    }

    private function assertMembership(int $ownerBusinessId, int $marketplaceId, int $businessId): void
    {
        if ($businessId === $ownerBusinessId) {
            return;
        }

        $membership = $this->members->findByMarketplaceAndBusiness($marketplaceId, $businessId);

        if (! $membership || $membership->status() !== PrivateMarketplaceMemberStatus::Active) {
            throw new InvalidArgumentException("Business [{$businessId}] is not an active member of Private Marketplace [{$marketplaceId}].");
        }
    }
}
