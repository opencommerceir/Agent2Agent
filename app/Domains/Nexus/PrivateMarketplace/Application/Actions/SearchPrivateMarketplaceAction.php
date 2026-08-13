<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\Actions;

use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\PrivateMarketplace\Infrastructure\Queries\PrivateMarketplaceSearchQuery;

/**
 * The member-gated counterpart to Marketplace's own SearchMarketplaceAction
 * — charged before the search runs (same CostGate-first ordering), then
 * delegates to PrivateMarketplaceSearchQuery, which itself returns an empty
 * list for a non-member rather than raising an exception (searching a
 * marketplace you're not part of isn't an error, it's just empty — same
 * "no results" shape a public search returns for an unmatched query).
 */
final class SearchPrivateMarketplaceAction
{
    public function __construct(
        private readonly PrivateMarketplaceSearchQuery $query,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    /**
     * @return array{listings: array<int, array>}
     */
    public function execute(int $marketplaceId, int $callingBusinessId): array
    {
        $this->costGate->execute($callingBusinessId, 'nexus.private_marketplace.search');

        $listings = $this->query->listingsVisibleTo($marketplaceId, $callingBusinessId);

        return [
            'listings' => array_map(fn ($listing) => $listing->toArray(), $listings),
        ];
    }
}
