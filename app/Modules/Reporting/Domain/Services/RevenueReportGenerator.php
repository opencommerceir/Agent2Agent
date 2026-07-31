<?php

namespace App\Modules\Reporting\Domain\Services;

/**
 * Pure, framework-free — the single formula owner for "net revenue".
 * `net_revenue = gross_revenue - discounts_applied` — deliberately does
 * NOT subtract `tax_collected`: tax is money collected on behalf of a
 * tax authority, not revenue the business keeps or loses, so it's
 * reported alongside gross/net but never netted against either
 * (mirrors Commerce's own `PricingService` formula ownership: this is
 * the one place that decides what "net" means for a Revenue Report,
 * so no two Actions can compute it differently).
 */
final class RevenueReportGenerator
{
    /**
     * @return array{grossRevenue: int, taxCollected: int, discountsApplied: int, netRevenue: int}
     */
    public function generate(int $grossRevenue, int $taxCollected, int $discountsApplied): array
    {
        return [
            'grossRevenue' => $grossRevenue,
            'taxCollected' => $taxCollected,
            'discountsApplied' => $discountsApplied,
            'netRevenue' => $grossRevenue - $discountsApplied,
        ];
    }
}
