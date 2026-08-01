<?php

namespace App\Modules\Analytics\Application\DTOs;

/**
 * The "6 main KPI cards + Top 5 Products + Recent Orders" shape the
 * Dashboard Home page (Phase 4 Stage 5, extended this stage) and
 * `analytics.dashboard.stats` both return. Assembled by
 * `GetDashboardStatsAction` from several KPI calculations at once — there
 * is no single Domain Entity behind it (the same "computed, not
 * persisted, own DTO" shape Reporting's own `SalesReportData` etc.
 * already establish).
 */
final class DashboardStatsData
{
    public function __construct(
        public readonly int $totalRevenueCents,
        public readonly string $currency,
        public readonly int $totalOrders,
        public readonly int $averageOrderValueCents,
        public readonly int $totalCustomers,
        public readonly float $conversionRatePercent,
        public readonly int $activeLoyaltyAccounts,
        public readonly array $topProducts,
        public readonly array $recentOrders,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'totalRevenueCents' => $this->totalRevenueCents,
            'currency' => $this->currency,
            'totalOrders' => $this->totalOrders,
            'averageOrderValueCents' => $this->averageOrderValueCents,
            'totalCustomers' => $this->totalCustomers,
            'conversionRatePercent' => $this->conversionRatePercent,
            'activeLoyaltyAccounts' => $this->activeLoyaltyAccounts,
            'topProducts' => $this->topProducts,
            'recentOrders' => $this->recentOrders,
        ];
    }
}
