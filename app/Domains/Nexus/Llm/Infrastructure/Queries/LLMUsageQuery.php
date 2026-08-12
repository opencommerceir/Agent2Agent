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
}
