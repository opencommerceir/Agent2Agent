<?php

namespace App\Domains\Nexus\Marketplace\Application\Actions;

use App\Domains\Nexus\Marketplace\Infrastructure\Queries\BusinessSearchQuery;
use App\Domains\Nexus\Reputation\Application\Actions\CalculateReputationScoreAction;

/**
 * Ranks a set of candidate suppliers (typically SearchMarketplaceAction's
 * own output). Phase 2/M1 ranked by catalog item count — the one honest
 * signal available before Reputation (Phase 6) existed. Phase 8/M3 (AI
 * Recommendations) upgrades the primary sort key to the real reputation
 * score now that it exists; catalog item count remains the tie-breaker
 * (still a meaningful secondary signal — a wider catalog among equally
 * reputable suppliers), and businessId stays the final, fully
 * deterministic tie-breaker.
 */
final class RankSuppliersAction
{
    public function __construct(
        private readonly BusinessSearchQuery $businesses,
        private readonly CalculateReputationScoreAction $calculateReputationScore,
    ) {
    }

    /**
     * @param  list<int>  $businessIds
     * @return array{listings: array<int, array>}
     */
    public function execute(array $businessIds): array
    {
        $scores = collect($businessIds)->mapWithKeys(
            fn (int $id) => [$id => $this->calculateReputationScore->execute($id)->score]
        );

        $listings = $this->businesses->listingsFor($businessIds)
            ->sortBy([
                fn ($a, $b) => $scores[$b->businessId] <=> $scores[$a->businessId],
                fn ($a, $b) => (count($b->products) + count($b->services)) <=> (count($a->products) + count($a->services)),
                fn ($a, $b) => $a->businessId <=> $b->businessId,
            ])
            ->values();

        return [
            'listings' => $listings->map(fn ($listing) => $listing->toArray())->all(),
        ];
    }
}
