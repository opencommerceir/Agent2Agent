<?php

namespace App\Domains\Nexus\Marketplace\Application\Actions;

use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Marketplace\Infrastructure\Queries\BusinessSearchQuery;

/**
 * "Discovery" — verified businesses (and their matching catalog items)
 * a calling Business's Agent can go negotiate with. Excludes the caller's
 * own business; a Business cannot discover or negotiate with itself.
 * Gated by the CostGate (Phase 3/M2, nexus.marketplace.search in
 * config('nexus.platform.credit.action_costs')) — charged before the
 * search runs, so a Business with no credit is stopped before spending
 * any query effort, not after.
 */
final class SearchMarketplaceAction
{
    public function __construct(
        private readonly BusinessSearchQuery $businesses,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    /**
     * @return array{listings: array<int, array>}
     */
    public function execute(int $callingBusinessId, ?string $query = null, ?string $industry = null): array
    {
        $this->costGate->execute($callingBusinessId, 'nexus.marketplace.search');

        $listings = $this->businesses->search($callingBusinessId, $query, $industry);

        return [
            'listings' => array_map(fn ($listing) => $listing->toArray(), $listings),
        ];
    }
}
