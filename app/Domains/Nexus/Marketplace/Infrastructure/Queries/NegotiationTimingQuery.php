<?php

namespace App\Domains\Nexus\Marketplace\Infrastructure\Queries;

use App\Domains\Nexus\Negotiation\Infrastructure\Models\Negotiation;

/**
 * A plain, autowired concrete Query class — not a Repository — reading
 * Negotiation outcomes for the "optimal timing" AI Recommendation (Phase
 * 8/M3, roadmap: "زمان‌بندی بهینه"). Same "Infrastructure\Queries\*"
 * convention BusinessSearchQuery/NetworkQuery already establish for
 * Marketplace.
 *
 * The only honest "timing" signal this codebase has is WHICH DAY OF THE
 * WEEK a Negotiation targeting a given counterparty historically closed
 * Accepted vs Rejected — a real, timestamped outcome, unlike the
 * "response time" signal ReputationQuery's own docblock already refused to
 * fabricate (Phase 6/M2) for being trivially gameable. Day-of-week
 * acceptance rate is neither gameable nor invented: it's exactly what
 * `negotiations.created_at` + `negotiations.status` already record.
 */
class NegotiationTimingQuery
{
    /**
     * @return list<array{dayOfWeek: int, dealCount: int, acceptedCount: int, acceptanceRatePercent: float}>
     */
    public function acceptanceRateByDayOfWeek(int $counterpartyBusinessId): array
    {
        $negotiations = Negotiation::query()
            ->where('counterparty_business_id', $counterpartyBusinessId)
            ->whereIn('status', ['accepted', 'rejected'])
            ->get(['created_at', 'status']);

        $byDay = [];

        foreach ($negotiations as $negotiation) {
            // ISO-8601 day of week: 1 (Monday) .. 7 (Sunday) — locale-free,
            // unlike Carbon's own dayOfWeek (0=Sunday) which shifts meaning
            // depending on the app's configured start-of-week.
            $day = (int) $negotiation->created_at->isoFormat('E');
            $byDay[$day] ??= ['dealCount' => 0, 'acceptedCount' => 0];
            $byDay[$day]['dealCount']++;

            if ($negotiation->status === 'accepted') {
                $byDay[$day]['acceptedCount']++;
            }
        }

        ksort($byDay);

        return array_map(fn (int $day) => [
            'dayOfWeek' => $day,
            'dealCount' => $byDay[$day]['dealCount'],
            'acceptedCount' => $byDay[$day]['acceptedCount'],
            'acceptanceRatePercent' => round($byDay[$day]['acceptedCount'] / $byDay[$day]['dealCount'] * 100, 1),
        ], array_keys($byDay));
    }
}
