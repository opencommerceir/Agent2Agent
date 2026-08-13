<?php

namespace App\Domains\Nexus\Marketplace\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Marketplace\Infrastructure\Queries\BusinessSearchQuery;
use App\Domains\Nexus\Reputation\Application\Actions\CalculateReputationScoreAction;
use InvalidArgumentException;

/**
 * "You might want to negotiate with these" — same-industry verified
 * businesses. Candidate membership is still the same-industry heuristic
 * (Phase 2/M1) — nothing richer exists to widen the *set* by — but ranking
 * *within* that set was upgraded in Phase 8/M1 (AI Recommendations) to
 * reputation score (Phase 6) now that it exists, instead of the arbitrary
 * `latest('id')` order BusinessSearchQuery::sameIndustry() used to be the
 * final word on. This is exactly the upgrade path this Action's own
 * original docblock already predicted ("the simplest honest heuristic
 * available before Reputation exists").
 */
final class GetRecommendationsAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businessRepository,
        private readonly BusinessSearchQuery $businesses,
        private readonly CalculateReputationScoreAction $calculateReputationScore,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    /**
     * @return array{listings: array<int, array>}
     */
    public function execute(int $businessId, int $limit = 5): array
    {
        $business = $this->businessRepository->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $this->costGate->execute($businessId, 'nexus.marketplace.recommendations');

        // Over-fetch the same-industry candidate pool so reputation can
        // actually re-rank it before the final $limit is applied — asking
        // BusinessSearchQuery for only $limit rows up front would freeze
        // in its own (arbitrary) latest-first order first.
        $listings = $this->businesses->sameIndustry($businessId, $business->industry()->value, $limit * 4);

        $ranked = collect($listings)
            ->map(fn ($listing) => ['listing' => $listing, 'score' => $this->calculateReputationScore->execute($listing->businessId)->score])
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('listing');

        return [
            'listings' => $ranked->map(fn ($listing) => $listing->toArray())->values()->all(),
        ];
    }
}
