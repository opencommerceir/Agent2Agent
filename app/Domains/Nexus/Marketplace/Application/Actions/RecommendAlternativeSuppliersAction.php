<?php

namespace App\Domains\Nexus\Marketplace\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Marketplace\Infrastructure\Queries\BusinessSearchQuery;
use App\Domains\Nexus\Reputation\Application\Actions\CalculateReputationScoreAction;
use InvalidArgumentException;

/**
 * "Alternatives" (Phase 8/M3, roadmap: "جایگزین‌ها") — other verified,
 * reputation-ranked Businesses in the same industry as a specific supplier
 * a Business is already considering, excluding that supplier itself and
 * the caller. Same candidate-set heuristic GetRecommendationsAction already
 * uses (same-industry membership), just anchored to a named supplier's
 * industry instead of the caller's own — the natural "who else sells what
 * this supplier sells" question, not a new discovery mechanism.
 */
final class RecommendAlternativeSuppliersAction
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
    public function execute(int $callingBusinessId, int $targetSupplierBusinessId, int $limit = 5): array
    {
        $target = $this->businessRepository->findById($targetSupplierBusinessId);

        if (! $target) {
            throw new InvalidArgumentException("Business [{$targetSupplierBusinessId}] does not exist.");
        }

        $this->costGate->execute($callingBusinessId, 'nexus.marketplace.alternatives');

        $candidates = $this->businesses->sameIndustry($callingBusinessId, $target->industry()->value, $limit * 4 + 1);

        $ranked = collect($candidates)
            ->filter(fn ($listing) => $listing->businessId !== $targetSupplierBusinessId)
            ->map(fn ($listing) => ['listing' => $listing, 'score' => $this->calculateReputationScore->execute($listing->businessId)->score])
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('listing');

        return [
            'listings' => $ranked->map(fn ($listing) => $listing->toArray())->values()->all(),
        ];
    }
}
