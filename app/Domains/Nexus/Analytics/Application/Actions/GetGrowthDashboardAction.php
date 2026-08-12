<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Analytics\Application\DTOs\GrowthDashboardData;
use App\Domains\Nexus\Analytics\Infrastructure\Queries\GrowthAnalyticsQuery;
use DateTimeInterface;

/**
 * Read model for the admin Viral Analytics dashboard (Phase 5/M5,
 * roadmap: "ردیابی K-factor، Cohort analysis و A/B testing"). Same
 * "Controller -> one Action -> a Query class" shape GetRevenueDashboardAction
 * (Phase 3/M6) already established for Analytics.
 *
 * K-factor = (average invites sent per inviting Business) × (conversion
 * rate of those invites) — the textbook viral-loop formula. Zero invites
 * sent in the period is 0.0, not a division error, same "no previous
 * revenue returns null not a division error" honesty
 * RevenueCalculatorTest already covers for the Revenue dashboard's own
 * growth-rate metric.
 */
final class GetGrowthDashboardAction
{
    public function __construct(
        private readonly GrowthAnalyticsQuery $query,
    ) {
    }

    public function execute(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): GrowthDashboardData
    {
        $totals = $this->query->inviteTotals($from, $to);

        $avgInvitesPerBusiness = $totals['invitingBusinesses'] > 0
            ? $totals['sent'] / $totals['invitingBusinesses']
            : 0.0;

        $conversionRate = $totals['sent'] > 0
            ? $totals['converted'] / $totals['sent']
            : 0.0;

        return new GrowthDashboardData(
            kFactor: round($avgInvitesPerBusiness * $conversionRate, 2),
            invitesSent: $totals['sent'],
            invitesConverted: $totals['converted'],
            conversionRatePercent: round($conversionRate * 100, 1),
            invitingBusinesses: $totals['invitingBusinesses'],
            cohorts: $this->query->cohorts($from, $to),
            variants: $this->query->inviteVariants($from, $to),
        );
    }
}
