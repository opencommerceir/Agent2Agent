<?php

namespace App\Domains\Nexus\Marketplace\Application\Actions;

use App\Domains\Nexus\Marketplace\Infrastructure\Queries\BusinessSearchQuery;

/**
 * Ranks a set of candidate suppliers (typically SearchMarketplaceAction's
 * own output). No Reputation score exists yet (Phase 6), so ranking is
 * the one honest signal available today — how many catalog items a
 * supplier actually offers, most first — rather than pretending to have
 * a real trust/quality score.
 */
final class RankSuppliersAction
{
    public function __construct(
        private readonly BusinessSearchQuery $businesses,
    ) {
    }

    /**
     * @param  list<int>  $businessIds
     * @return array{listings: array<int, array>}
     */
    public function execute(array $businessIds): array
    {
        $listings = $this->businesses->listingsFor($businessIds)
            ->sortBy([
                fn ($a, $b) => (count($b->products) + count($b->services)) <=> (count($a->products) + count($a->services)),
                fn ($a, $b) => $a->businessId <=> $b->businessId,
            ])
            ->values();

        return [
            'listings' => $listings->map(fn ($listing) => $listing->toArray())->all(),
        ];
    }
}
