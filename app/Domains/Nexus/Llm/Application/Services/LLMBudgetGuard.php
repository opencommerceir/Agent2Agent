<?php

namespace App\Domains\Nexus\Llm\Application\Services;

use App\Domains\Nexus\Llm\Domain\Exceptions\BudgetLimitExceededException;
use App\Domains\Nexus\Llm\Infrastructure\Queries\LLMUsageQuery;

/**
 * Real enforcement of docs/claude/llm-strategy.md §9's daily/monthly LLM
 * budgets. No-ops (never checks) for a free/local provider candidate —
 * "block paid providers, force local only" is achieved for free this way,
 * with no special-casing in LLMRouter's own selection loop: a paid
 * candidate over budget throws here exactly like a broken provider would,
 * and the router's existing try/next-candidate loop advances past it the
 * same way. Also no-ops when both `$agentId` and `$businessId` are null
 * (e.g. an admin "test connection" ping) — there's no context to check a
 * budget against, and an admin must always be able to verify a provider
 * works, even mid-outage or over-budget.
 *
 * **Unit-boundary decision, explicit on purpose** (same discipline
 * App\Domains\Nexus\Analytics\Infrastructure\Queries\RevenueQuery's own
 * docblock already uses for its Credit/Escrow subunit mismatch): budgets
 * are configured/displayed in Toman (IRT, matching `credit.currency`), but
 * `LLMUsageLog.chargedCostUsd` is stored in USD (the currency every real
 * provider actually bills in). This class is the single, explicit
 * conversion point (`* usd_to_irt_rate`) — any future caller comparing LLM
 * cost figures across the IRT/USD boundary must convert here, not invent a
 * second conversion elsewhere.
 */
final class LLMBudgetGuard
{
    public function __construct(
        private readonly LLMUsageQuery $usage,
        private readonly LLMSettingsService $settings,
    ) {
    }

    public function assertWithinBudget(?int $agentId, ?int $businessId, string $providerId, float $estimatedCostUsd): void
    {
        if (config("nexus.platform.llm.provider_tiers.{$providerId}", 'paid') !== 'paid') {
            return;
        }

        if ($agentId === null && $businessId === null) {
            return;
        }

        $usdToIrtRate = (float) config('nexus.platform.llm.cost_control.usd_to_irt_rate', 0);
        $estimatedCostIrt = $estimatedCostUsd * $usdToIrtRate;

        if ($agentId !== null) {
            $dailyBudgetIrt = $this->settings->dailyBudgetPerAgentIrt();
            $spentTodayIrt = $this->usage->sumChargedCostUsdForAgentToday($agentId) * $usdToIrtRate;

            if ($dailyBudgetIrt > 0 && ($spentTodayIrt + $estimatedCostIrt) > $dailyBudgetIrt) {
                throw new BudgetLimitExceededException(
                    "Agent [{$agentId}] would exceed its daily LLM budget of {$dailyBudgetIrt} IRT.",
                );
            }
        }

        if ($businessId !== null) {
            $monthlyBudgetIrt = $this->settings->monthlyBudgetPerBusinessIrt();
            $spentThisMonthIrt = $this->usage->sumChargedCostUsdForBusinessThisMonth($businessId) * $usdToIrtRate;

            if ($monthlyBudgetIrt > 0 && ($spentThisMonthIrt + $estimatedCostIrt) > $monthlyBudgetIrt) {
                throw new BudgetLimitExceededException(
                    "Business [{$businessId}] would exceed its monthly LLM budget of {$monthlyBudgetIrt} IRT.",
                );
            }
        }
    }
}
