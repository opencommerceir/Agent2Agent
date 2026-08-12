<?php

namespace App\Domains\Nexus\Marketplace\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Marketplace\Infrastructure\Queries\BusinessSearchQuery;
use InvalidArgumentException;

/**
 * "You might want to negotiate with these" — same-industry verified
 * businesses. The simplest honest heuristic available before Reputation
 * (Phase 6) exists to rank by anything richer; not under-building, there
 * is nothing else meaningful to recommend by yet.
 */
final class GetRecommendationsAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businessRepository,
        private readonly BusinessSearchQuery $businesses,
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

        $listings = $this->businesses->sameIndustry($businessId, $business->industry()->value, $limit);

        return [
            'listings' => array_map(fn ($listing) => $listing->toArray(), $listings),
        ];
    }
}
