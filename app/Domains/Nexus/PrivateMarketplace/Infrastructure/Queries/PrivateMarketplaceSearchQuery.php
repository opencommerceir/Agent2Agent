<?php

namespace App\Domains\Nexus\PrivateMarketplace\Infrastructure\Queries;

use App\Domains\Nexus\Business\Infrastructure\Models\Business;
use App\Domains\Nexus\PrivateMarketplace\Application\DTOs\PrivateMarketplaceListingData;
use App\Domains\Nexus\PrivateMarketplace\Infrastructure\Models\PrivateMarketplace;
use App\Domains\Nexus\PrivateMarketplace\Infrastructure\Models\PrivateMarketplaceListing;
use App\Domains\Nexus\PrivateMarketplace\Infrastructure\Models\PrivateMarketplaceMember;

/**
 * A sibling to Marketplace's own BusinessSearchQuery, not a modification of
 * it — public discovery explicitly excludes membership-scoping, and a
 * Private Marketplace's whole point is the opposite: nothing is visible
 * without membership. "Confidential pricing" is enforced by this
 * membership gate on the query itself, not by encrypting the stored price —
 * there is no field-level crypto anywhere in this codebase to extend.
 */
class PrivateMarketplaceSearchQuery
{
    /**
     * Empty for anyone who isn't an Active member — including the
     * marketplace's own owner, who must join like any other member to see
     * listings (ownership only grants invite/archive rights, checked
     * separately by the Actions that need it).
     *
     * @return list<PrivateMarketplaceListingData>
     */
    public function listingsVisibleTo(int $privateMarketplaceId, int $callingBusinessId): array
    {
        $isOwner = PrivateMarketplace::query()
            ->where('id', $privateMarketplaceId)
            ->where('owner_business_id', $callingBusinessId)
            ->exists();

        $isActiveMember = PrivateMarketplaceMember::query()
            ->where('private_marketplace_id', $privateMarketplaceId)
            ->where('business_id', $callingBusinessId)
            ->where('status', 'active')
            ->exists();

        if (! $isOwner && ! $isActiveMember) {
            return [];
        }

        return PrivateMarketplaceListing::query()
            ->where('private_marketplace_id', $privateMarketplaceId)
            ->get()
            ->map(function (PrivateMarketplaceListing $listing) {
                $business = Business::query()->find($listing->listing_business_id);

                return new PrivateMarketplaceListingData(
                    id: $listing->id,
                    privateMarketplaceId: $listing->private_marketplace_id,
                    listingBusinessId: $listing->listing_business_id,
                    listingBusinessNameEn: $business?->name_en ?? "#{$listing->listing_business_id}",
                    catalogItemType: $listing->catalog_item_type,
                    catalogItemId: $listing->catalog_item_id,
                    specialPriceAmount: $listing->special_price_amount,
                    specialPriceCurrency: $listing->special_price_currency,
                    createdAt: $listing->created_at->format(DATE_ATOM),
                );
            })
            ->all();
    }
}
