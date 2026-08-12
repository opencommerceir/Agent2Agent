<?php

namespace App\Domains\Nexus\Reputation\Infrastructure\Queries;

use App\Domains\Nexus\Contract\Infrastructure\Models\Escrow;
use App\Domains\Nexus\Negotiation\Infrastructure\Models\Negotiation;
use App\Domains\Nexus\Reputation\Infrastructure\Models\Review;
use DateTimeImmutable;

/**
 * A plain, autowired concrete Query class — not a Repository — reading
 * across Negotiation/Contract/Reputation for the reputation score, the
 * same "Infrastructure\Queries\*" convention RevenueQuery/GrowthAnalyticsQuery
 * already establish for cross-domain read projections.
 *
 * Deliberately excludes a "response time" signal even though the roadmap
 * mentions it — nothing in this codebase timestamps a business's own
 * reply latency in a way that isn't trivially gameable (a counter-offer's
 * created_at only proves *a* message arrived, not that the right party
 * was responsive) — same "don't fabricate a signal that isn't honestly
 * there" reasoning GrowthAnalyticsQuery's own Cohort-by-registration-week
 * substitution already established (Phase 5/M5) when `verified_at` turned
 * out not to exist either.
 */
class ReputationQuery
{
    /**
     * Accepted / (Accepted + Rejected) among Negotiations where the
     * Business is a party — Proposed/Countered/PendingApproval are still
     * open, not yet a success-or-failure outcome, so they're excluded
     * rather than counted against either side.
     */
    public function successRate(int $businessId): float
    {
        $query = Negotiation::query()
            ->where(fn ($q) => $q->where('initiator_business_id', $businessId)->orWhere('counterparty_business_id', $businessId))
            ->whereIn('status', ['accepted', 'rejected']);

        $total = $query->count();

        if ($total === 0) {
            return 0.0;
        }

        $accepted = (clone $query)->where('status', 'accepted')->count();

        return $accepted / $total;
    }

    /**
     * @return array{average: float, count: int}
     */
    public function ratingSummary(int $businessId): array
    {
        $query = Review::query()->where('reviewee_business_id', $businessId)->where('status', 'published');
        $count = $query->count();

        return [
            'average' => $count > 0 ? round((float) $query->avg('rating'), 2) : 0.0,
            'count' => $count,
        ];
    }

    /**
     * Escrows actually Released (paid AND delivered), not merely Held —
     * same "only count a deal once it's genuinely done" honesty
     * RevenueQuery's own escrowFeeRevenue()/escrowPending() split follows.
     */
    public function completedDealsCount(int $businessId): int
    {
        return Escrow::query()
            ->where('status', 'released')
            ->where(fn ($q) => $q->where('business_a_id', $businessId)->orWhere('business_b_id', $businessId))
            ->count();
    }

    public function longevityMonths(int $businessId, DateTimeImmutable $createdAt): int
    {
        $now = new DateTimeImmutable();
        $diff = $createdAt->diff($now);

        return max(0, ($diff->y * 12) + $diff->m);
    }
}
