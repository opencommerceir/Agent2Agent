<?php

namespace App\Domains\Nexus\Reputation\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Reputation\Application\DTOs\ReputationScoreData;
use App\Domains\Nexus\Reputation\Infrastructure\Queries\ReputationQuery;
use InvalidArgumentException;

/**
 * A pure read-model, computed live from real signals — no ReputationScore
 * table exists and none is needed, the same "Query class over existing
 * data, don't duplicate it into a new mutable row" reasoning
 * RevenueQuery/GrowthAnalyticsQuery already establish. 0-1000, weighted
 * from config('nexus.platform.reputation.weights') so an admin can retune
 * without a deploy (read once per call, not hot-reloadable like
 * MarginSettingsService — nothing here has ever needed changing without a
 * restart, unlike margin.* which is genuinely admin-editable via a
 * dedicated settings page).
 *
 * The rating component is 0 when reviewCount is 0 (no data yet is not the
 * same as a mediocre 2.5/5) — a Business earns this component, it doesn't
 * start with a free average. Badges are derived, never stored: Verified
 * reuses Business::isVerified() (Phase 1) rather than a second concept of
 * "verified," TopRated/GoldPartner are pure threshold checks over the
 * same numbers already computed here.
 *
 * Phase 6/M3 adds a dispute penalty AFTER the three weighted components
 * are summed (not baked into the weights themselves) — a capped
 * deduction per DisputeCase actually ruled against this Business
 * (ReputationQuery::disputesLostCount(), never merely "was involved in a
 * dispute" — raising or receiving one that resolves in your favor costs
 * nothing), floored at 0.
 */
final class CalculateReputationScoreAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly ReputationQuery $reputation,
    ) {
    }

    public function execute(int $businessId): ReputationScoreData
    {
        $business = $this->businesses->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $weights = config('nexus.platform.reputation.weights');
        $longevityFullMonths = (int) config('nexus.platform.reputation.longevity_full_months');

        $successRate = $this->reputation->successRate($businessId);
        $ratingSummary = $this->reputation->ratingSummary($businessId);
        $completedDeals = $this->reputation->completedDealsCount($businessId);
        $longevityMonths = $this->reputation->longevityMonths($businessId, $business->createdAt());
        $disputesLost = $this->reputation->disputesLostCount($businessId);

        $successRateComponent = $successRate * $weights['success_rate'];
        $ratingComponent = $ratingSummary['count'] > 0
            ? ($ratingSummary['average'] / 5) * $weights['rating']
            : 0.0;
        $longevityComponent = min($longevityMonths / max($longevityFullMonths, 1), 1.0) * $weights['longevity'];

        $penalty = min(
            $disputesLost * (int) config('nexus.platform.reputation.dispute_penalty_per_loss'),
            (int) config('nexus.platform.reputation.dispute_penalty_max'),
        );

        $score = max(0, (int) round($successRateComponent + $ratingComponent + $longevityComponent) - $penalty);

        $badges = $this->deriveBadges($business->isVerified(), $score, $ratingSummary, $completedDeals);

        return new ReputationScoreData(
            businessId: $businessId,
            score: $score,
            successRate: $successRate,
            averageRating: $ratingSummary['average'],
            reviewCount: $ratingSummary['count'],
            completedDeals: $completedDeals,
            longevityMonths: $longevityMonths,
            disputesLost: $disputesLost,
            badges: $badges,
        );
    }

    /**
     * @param  array{average: float, count: int}  $ratingSummary
     * @return list<string>
     */
    private function deriveBadges(bool $isVerified, int $score, array $ratingSummary, int $completedDeals): array
    {
        $badgeConfig = config('nexus.platform.reputation.badges');
        $badges = [];

        if ($isVerified) {
            $badges[] = 'verified';
        }

        if ($ratingSummary['count'] >= $badgeConfig['top_rated_min_reviews'] && $ratingSummary['average'] >= $badgeConfig['top_rated_min_average']) {
            $badges[] = 'top_rated';
        }

        if ($score >= $badgeConfig['gold_partner_min_score'] && $completedDeals >= $badgeConfig['gold_partner_min_deals']) {
            $badges[] = 'gold_partner';
        }

        return $badges;
    }
}
