<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Analytics\Infrastructure\Queries\RevenueQuery;
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
 * "Net revenue" = creditPackageRevenue + escrowFeeRevenue (the two real
 * income streams docs/claude/monetization.md names) — there is no
 * separate "cost" to subtract yet (LLM cost tracking is Phase 4's own
 * territory), so gross and net are the same figure today; kept as two
 * separate keys so a future Phase 4 cost figure has somewhere to slot in
 * without reshaping this Action's return type.
 */
final class GetRevenueDashboardAction
{
    public function __construct(
        private readonly RevenueQuery $revenue,
    ) {
    }

    /**
     * @return array{
     *     creditPackageRevenue: array{amount: int, count: int},
     *     escrowFeeRevenue: array{amount: int, count: int},
     *     escrowPending: array{grossAmount: int, count: int},
     *     grossRevenue: int,
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
        $total = $creditPackageRevenue['amount'] + $escrowFeeRevenue['amount'];

        return [
            'creditPackageRevenue' => $creditPackageRevenue,
            'escrowFeeRevenue' => $escrowFeeRevenue,
            'escrowPending' => $this->revenue->escrowPending($from, $to),
            'grossRevenue' => $total,
            'netRevenue' => $total,
            'creditsDeducted' => $this->revenue->creditsDeducted($from, $to),
            'perBusiness' => $this->revenue->perBusiness($from, $to),
            'perIndustry' => $this->revenue->perIndustry($from, $to),
            'perDay' => $this->revenue->perDay($from, $to),
        ];
    }
}
