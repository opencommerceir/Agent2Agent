<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Queries;

use App\Domains\Nexus\Llm\Infrastructure\Models\LLMUsageLog;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Raw aggregate SQL over `nexus_llm_usage_logs`, kept out of
 * LLMUsageLogRepositoryInterface on purpose — same reasoning
 * App\Domains\Nexus\Analytics\Infrastructure\Queries\RevenueQuery already
 * documents for keeping aggregate reads off a repository interface meant
 * for entity persistence.
 *
 * Both sums only count real usage (`business_id`/`agent_id` not null) —
 * admin "test connection" pings (both null) are excluded by construction,
 * without any extra filter, since they never carry either id.
 */
final class LLMUsageQuery
{
    public function sumChargedCostUsdForAgentToday(int $agentId): float
    {
        return (float) LLMUsageLog::query()
            ->where('agent_id', $agentId)
            ->where('created_at', '>=', Carbon::today())
            ->sum('charged_cost_usd');
    }

    public function sumChargedCostUsdForBusinessThisMonth(int $businessId): float
    {
        return (float) LLMUsageLog::query()
            ->where('business_id', $businessId)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('charged_cost_usd');
    }

    /**
     * Backs the Admin LLM Switcher's "over budget" banner (Phase 4/M7) —
     * a reduced version of llm-strategy.md §12's full monitoring
     * dashboard (per-provider/per-feature charts): just "is anyone over
     * right now", computed on page load rather than persisted/alerted.
     * Fetches per-agent sums in PHP rather than `groupBy()->having()->exists()`
     * to avoid grouped-query/exists() interaction differences across DB
     * drivers.
     */
    public function anyAgentOverDailyBudget(int $dailyBudgetIrt, float $usdToIrtRate): bool
    {
        if ($dailyBudgetIrt <= 0 || $usdToIrtRate <= 0) {
            return false;
        }

        $maxSpentUsd = LLMUsageLog::query()
            ->whereNotNull('agent_id')
            ->where('created_at', '>=', Carbon::today())
            ->selectRaw('agent_id, SUM(charged_cost_usd) as total')
            ->groupBy('agent_id')
            ->get()
            ->max('total');

        return $maxSpentUsd !== null && ($maxSpentUsd * $usdToIrtRate) > $dailyBudgetIrt;
    }

    public function anyBusinessOverMonthlyBudget(int $monthlyBudgetIrt, float $usdToIrtRate): bool
    {
        if ($monthlyBudgetIrt <= 0 || $usdToIrtRate <= 0) {
            return false;
        }

        $maxSpentUsd = LLMUsageLog::query()
            ->whereNotNull('business_id')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->selectRaw('business_id, SUM(charged_cost_usd) as total')
            ->groupBy('business_id')
            ->get()
            ->max('total');

        return $maxSpentUsd !== null && ($maxSpentUsd * $usdToIrtRate) > $monthlyBudgetIrt;
    }

    /**
     * Backs the Revenue Dashboard's real "net revenue" (Phase 3/M6's own
     * docblock named exactly this as Phase 4's territory to fill in).
     * Deliberately sums `real_cost_usd`, not `charged_cost_usd` — the
     * markup is never actually billed to any Business anywhere in this
     * codebase (no CostGate/SpendCreditsForActionAction call references
     * charged_cost_usd), so it isn't a real expense to net against
     * revenue; `real_cost_usd` is what the platform actually pays each
     * LLM provider, the genuine cost side of gross-minus-cost. Mirrors
     * RevenueQuery::applyRange's own from/to filtering on `created_at`.
     */
    public function sumRealCostUsdForRange(?DateTimeInterface $from, ?DateTimeInterface $to): float
    {
        $query = LLMUsageLog::query();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return (float) $query->sum('real_cost_usd');
    }
}
