<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Queries;

use App\Domains\Nexus\Llm\Infrastructure\Models\LLMUsageLog;
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
}
