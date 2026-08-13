<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Analytics\Infrastructure\Queries\BusinessAnalyticsQuery;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Reputation\Infrastructure\Queries\ReputationQuery;
use InvalidArgumentException;

/**
 * Read model for the business-portal Analytics page (Phase 8/M1, roadmap:
 * "Business Analytics"). Same "Controller -> one Action -> Query class(es)"
 * shape GetRevenueDashboardAction/GetGrowthDashboardAction already
 * established for Analytics — this one composes TWO Query classes from two
 * different domains (BusinessAnalyticsQuery here, ReputationQuery from
 * Reputation), exactly the read-model cross-domain carve-out
 * docs/nexus/nexus_handoff.md's Phase 1/M6 decision already documents.
 *
 * successRate/completedDeals are NOT recomputed here — ReputationQuery
 * already owns that exact math (Phase 6/M2); reusing it keeps this
 * dashboard's numbers identical to the Reputation Score shown elsewhere for
 * the same Business, rather than risking two subtly different formulas.
 */
final class GetBusinessAnalyticsAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly BusinessAnalyticsQuery $analytics,
        private readonly ReputationQuery $reputation,
    ) {
    }

    /**
     * @return array{
     *     successRate: float,
     *     completedDeals: int,
     *     dealCounts: array{accepted: int, rejected: int, expired: int, open: int},
     *     savings: array{totalsByCurrency: array<string, int>, dealCount: int, deals: list<array>},
     *     priceBenchmark: array{product: array, service: array}
     * }
     */
    public function execute(int $businessId): array
    {
        $business = $this->businesses->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $minSampleSize = (int) config('nexus.platform.analytics.min_benchmark_sample_size');

        return [
            'successRate' => $this->reputation->successRate($businessId),
            'completedDeals' => $this->reputation->completedDealsCount($businessId),
            'dealCounts' => $this->analytics->dealOutcomeCounts($businessId),
            'savings' => $this->analytics->savingsFromNegotiations($businessId),
            'priceBenchmark' => $this->analytics->industryPriceBenchmark($businessId, $business->industry()->value, $minSampleSize),
        ];
    }
}
