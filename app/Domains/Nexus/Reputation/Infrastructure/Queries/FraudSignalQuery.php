<?php

namespace App\Domains\Nexus\Reputation\Infrastructure\Queries;

use App\Domains\Nexus\Contract\Infrastructure\Models\DisputeCase;
use Illuminate\Support\Carbon;

/**
 * Rule-based fraud detection (docs/claude/llm-strategy.md's "Rule Engine
 * 80%, zero cost" default — no ML/anomaly-detection infra exists in this
 * codebase, an honest limitation not an oversight, the same stance
 * NegotiationReasoningService already takes for negotiation "thinking").
 * The only real, provable signal available is disputes an arbiter
 * actually ruled against a Business (ReputationQuery::disputesLostCount()'s
 * same underlying fact) within a rolling window — not raw dispute count
 * (being disputed and winning proves nothing wrong), not reject rate
 * (rejecting bad offers is healthy negotiation, not fraud).
 */
class FraudSignalQuery
{
    /**
     * @return list<int> businessIds
     */
    public function businessesExceedingDisputeLossThreshold(int $threshold, int $windowDays): array
    {
        if ($threshold <= 0) {
            return [];
        }

        $since = Carbon::now()->subDays($windowDays);

        $sellerLosses = DisputeCase::query()
            ->where('status', 'resolved')
            ->where('resolution', 'refund_buyer')
            ->where('resolved_at', '>=', $since)
            ->selectRaw('business_b_id as business_id, COUNT(*) as losses')
            ->groupBy('business_b_id');

        $buyerLosses = DisputeCase::query()
            ->where('status', 'resolved')
            ->where('resolution', 'release_seller')
            ->where('resolved_at', '>=', $since)
            ->selectRaw('business_a_id as business_id, COUNT(*) as losses')
            ->groupBy('business_a_id');

        $totals = [];
        foreach ($sellerLosses->get() as $row) {
            $totals[$row->business_id] = ($totals[$row->business_id] ?? 0) + $row->losses;
        }
        foreach ($buyerLosses->get() as $row) {
            $totals[$row->business_id] = ($totals[$row->business_id] ?? 0) + $row->losses;
        }

        return array_values(array_map('intval', array_keys(array_filter(
            $totals,
            fn (int $losses) => $losses >= $threshold,
        ))));
    }
}
