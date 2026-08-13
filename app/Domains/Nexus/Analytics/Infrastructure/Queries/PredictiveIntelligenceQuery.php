<?php

namespace App\Domains\Nexus\Analytics\Infrastructure\Queries;

use App\Domains\Nexus\Contract\Infrastructure\Models\DisputeCase;
use App\Domains\Nexus\Negotiation\Infrastructure\Models\Negotiation;
use DateTimeImmutable;

/**
 * A plain, autowired concrete Query class — not a Repository — reading
 * across Negotiation + Contract(DisputeCase) for Predictive Intelligence
 * (Phase 8/M5, roadmap: "پیش‌بینی اعتبار تامین‌کننده، ریسک‌سنجی معاملات").
 * Same "Infrastructure\Queries\*" convention every earlier Analytics Query
 * class already establishes.
 *
 * Every method here is a real, timestamped signal already in the
 * database — no ML model, no invented score. Matches the project-wide
 * "Rule Engine 80%, zero-cost by default" default (docs/claude/llm-strategy.md)
 * that NegotiationReasoningService (Phase 2/M5) and CalculateReputationScoreAction
 * (Phase 6/M2) already established for every other "prediction"-shaped
 * feature in this codebase.
 */
class PredictiveIntelligenceQuery
{
    /**
     * Accepted/(Accepted+Rejected) for two adjacent windows — "recent" vs
     * "prior" — so a caller can tell whether a Business's own success rate
     * is trending up, down, or flat, not just its current single number
     * (which ReputationQuery::successRate() already provides).
     *
     * @return array{recentRate: float|null, recentCount: int, priorRate: float|null, priorCount: int}
     */
    public function successRateTrend(int $businessId, DateTimeImmutable $now, int $recentWindowDays, int $priorWindowDays): array
    {
        $recentStart = $now->modify("-{$recentWindowDays} days");
        $priorStart = $recentStart->modify("-{$priorWindowDays} days");

        $party = fn ($q) => $q->where('initiator_business_id', $businessId)->orWhere('counterparty_business_id', $businessId);

        $recent = Negotiation::query()->where($party)->whereIn('status', ['accepted', 'rejected'])
            ->where('created_at', '>=', $recentStart)->where('created_at', '<=', $now)->get(['status']);

        $prior = Negotiation::query()->where($party)->whereIn('status', ['accepted', 'rejected'])
            ->where('created_at', '>=', $priorStart)->where('created_at', '<', $recentStart)->get(['status']);

        return [
            'recentRate' => $recent->count() > 0 ? round($recent->where('status', 'accepted')->count() / $recent->count(), 3) : null,
            'recentCount' => $recent->count(),
            'priorRate' => $prior->count() > 0 ? round($prior->where('status', 'accepted')->count() / $prior->count(), 3) : null,
            'priorCount' => $prior->count(),
        ];
    }

    /**
     * Resolved DisputeCases ruled against $businessId (same "lost", not
     * merely "involved", semantics ReputationQuery::disputesLostCount()
     * already establishes) within the last $windowDays — a recency-windowed
     * variant for risk scoring, where a dispute lost last week matters more
     * than one from a year ago.
     */
    public function disputesLostWithinDays(int $businessId, DateTimeImmutable $now, int $windowDays): int
    {
        $since = $now->modify("-{$windowDays} days");

        return DisputeCase::query()
            ->where('status', 'resolved')
            ->where('resolved_at', '>=', $since)
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('resolution', 'refund_buyer')->where('business_b_id', $businessId))
                ->orWhere(fn ($q2) => $q2->where('resolution', 'release_seller')->where('business_a_id', $businessId)))
            ->count();
    }

    /**
     * Average total deal size (unit price × quantity, in the Negotiation
     * domain's own minor-unit Money scale) across Accepted Negotiations
     * where $businessId is a party, grouped by currency — the "is this
     * deal unusually large for them" baseline AssessDealRiskAction needs.
     *
     * @return array<string, float>
     */
    public function averageDealSizeByCurrency(int $businessId): array
    {
        $negotiations = Negotiation::query()
            ->where(fn ($q) => $q->where('initiator_business_id', $businessId)->orWhere('counterparty_business_id', $businessId))
            ->where('status', 'accepted')
            ->get(['current_terms']);

        $byCurrency = [];

        foreach ($negotiations as $negotiation) {
            $terms = $negotiation->current_terms;
            $total = (int) $terms['priceAmount'] * (int) $terms['quantity'];
            $byCurrency[$terms['priceCurrency']][] = $total;
        }

        return array_map(fn (array $totals) => round(array_sum($totals) / count($totals), 2), $byCurrency);
    }

    /**
     * The average unit price a Business has actually accepted as the
     * COUNTERPARTY (seller) for a given catalog item type — Scenario
     * Planning's "what have they historically agreed to" baseline.
     *
     * @return array{averageUnitAmount: int, currency: string, dealCount: int}|null
     */
    public function averageAcceptedUnitPriceAsSeller(int $counterpartyBusinessId, string $catalogItemType): ?array
    {
        $negotiations = Negotiation::query()
            ->where('counterparty_business_id', $counterpartyBusinessId)
            ->where('catalog_item_type', $catalogItemType)
            ->where('status', 'accepted')
            ->get(['current_terms']);

        if ($negotiations->isEmpty()) {
            return null;
        }

        $currency = $negotiations->first()->current_terms['priceCurrency'];
        $sameCurrency = $negotiations->filter(fn ($n) => $n->current_terms['priceCurrency'] === $currency);

        return [
            'averageUnitAmount' => (int) round($sameCurrency->avg(fn ($n) => $n->current_terms['priceAmount'])),
            'currency' => $currency,
            'dealCount' => $sameCurrency->count(),
        ];
    }

    /**
     * Overall Accepted/(Accepted+Rejected) rate for Negotiations where
     * $counterpartyBusinessId was the counterparty being proposed to —
     * "how often do proposals TO them succeed," the base rate Scenario
     * Planning adjusts by price favorability.
     */
    public function acceptanceRateAsCounterparty(int $counterpartyBusinessId): ?float
    {
        $query = Negotiation::query()
            ->where('counterparty_business_id', $counterpartyBusinessId)
            ->whereIn('status', ['accepted', 'rejected']);

        $total = $query->count();

        if ($total === 0) {
            return null;
        }

        return round((clone $query)->where('status', 'accepted')->count() / $total, 3);
    }
}
