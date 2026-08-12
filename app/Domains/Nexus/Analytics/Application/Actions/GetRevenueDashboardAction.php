<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Analytics\Infrastructure\Queries\RevenueQuery;
use App\Domains\Nexus\Llm\Infrastructure\Queries\LLMUsageQuery;
use DateTimeInterface;

/**
 * The roadmap's admin Revenue Dashboard ("gross revenue, net revenue,
 * margins, costs... per business, per industry, per day") — a pure
 * read-model over RevenueQuery, the same "Controller -> one Action ->
 * typed shape" pattern app/Modules/Analytics's own GetDashboardStatsAction
 * establishes for the base Dashboard, and the same "reading across
 * domains for a display projection is fine, it's the query-side
 * counterpart" reasoning GetBusinessDashboardAction's own docblock
 * already established.
 *
 * "Gross revenue" = creditPackageRevenue + escrowFeeRevenue (the two real
 * income streams docs/claude/monetization.md names). "Net revenue" now
 * actually subtracts a real cost — LLMUsageQuery::sumRealCostUsdForRange(),
 * the platform's genuine LLM provider spend (Phase 4) — closing the gap
 * this Action's own docblock originally left open for exactly this. Same
 * USD->IRT conversion point convention LLMBudgetGuard established
 * (`config('nexus.platform.llm.cost_control.usd_to_irt_rate')`), since
 * Credit/Revenue amounts are Toman and LLMUsageLog cost is USD.
 */
final class GetRevenueDashboardAction
{
    public function __construct(
        private readonly RevenueQuery $revenue,
        private readonly LLMUsageQuery $llmUsage,
    ) {
    }

    /**
     * @return array{
     *     creditPackageRevenue: array{amount: int, count: int},
     *     escrowFeeRevenue: array{amount: int, count: int},
     *     escrowPending: array{grossAmount: int, count: int},
     *     grossRevenue: int,
     *     llmCost: array{amountUsd: float, amountIrt: int},
     *     netRevenue: int,
     *     creditsDeducted: int,
     *     perBusiness: list<array>,
     *     perIndustry: list<array>,
     *     perDay: list<array>,
     * }
     */
    public function execute(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): array
    {
        $creditPackageRevenue = $this->revenue->creditPackageRevenue($from, $to);
        $escrowFeeRevenue = $this->revenue->escrowFeeRevenue($from, $to);
        $gross = $creditPackageRevenue['amount'] + $escrowFeeRevenue['amount'];

        $usdToIrtRate = (float) config('nexus.platform.llm.cost_control.usd_to_irt_rate', 0);
        $llmCostUsd = $this->llmUsage->sumRealCostUsdForRange($from, $to);
        $llmCostIrt = (int) round($llmCostUsd * $usdToIrtRate);

        return [
            'creditPackageRevenue' => $creditPackageRevenue,
            'escrowFeeRevenue' => $escrowFeeRevenue,
            'escrowPending' => $this->revenue->escrowPending($from, $to),
            'grossRevenue' => $gross,
            'llmCost' => ['amountUsd' => $llmCostUsd, 'amountIrt' => $llmCostIrt],
            'netRevenue' => $gross - $llmCostIrt,
            'creditsDeducted' => $this->revenue->creditsDeducted($from, $to),
            'perBusiness' => $this->revenue->perBusiness($from, $to),
            'perIndustry' => $this->revenue->perIndustry($from, $to),
            'perDay' => $this->revenue->perDay($from, $to),
        ];
    }
}
