<?php

namespace App\Modules\Analytics\Application\Actions;

use App\Modules\Analytics\Application\DTOs\DashboardStatsData;
use App\Modules\Analytics\Domain\ValueObjects\TimePeriod;
use App\Modules\Commerce\Application\Actions\ListOrdersAction;
use App\Modules\Commerce\Application\DTOs\OrderData;
use DateTimeImmutable;

/**
 * The "6 main KPI cards + Top 5 Products + Recent Orders" the Dashboard
 * Home page shows (Phase 4 Stage 5, extended this stage) and
 * `analytics.dashboard.stats` both return — every number is "this
 * calendar month, to date", computed through the same `CalculateKPIAction`
 * every other KPI read uses. Recent Orders reuses Commerce's own
 * `ListOrdersAction` directly (the same Action `commerce.order.list`'s
 * MCP handler calls) rather than a new query.
 */
final class GetDashboardStatsAction
{
    private const RECENT_ORDERS_LIMIT = 5;

    public function __construct(
        private readonly CalculateKPIAction $calculateKPI,
        private readonly ListOrdersAction $listOrders,
    ) {
    }

    public function execute(int $tenantId): DashboardStatsData
    {
        [$start, $end] = TimePeriod::Monthly->boundsFor(new DateTimeImmutable('today'));
        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');

        $revenue = $this->calculateKPI->execute($tenantId, 'revenue', 'monthly', $startDate, $endDate);
        $orders = $this->calculateKPI->execute($tenantId, 'total_orders', 'monthly', $startDate, $endDate);
        $averageOrderValue = $this->calculateKPI->execute($tenantId, 'average_order_value', 'monthly', $startDate, $endDate);
        $customers = $this->calculateKPI->execute($tenantId, 'total_customers', 'monthly', $startDate, $endDate);
        $conversion = $this->calculateKPI->execute($tenantId, 'conversion_rate', 'monthly', $startDate, $endDate);
        $loyaltyAccounts = $this->calculateKPI->execute($tenantId, 'active_loyalty_accounts', 'monthly', $startDate, $endDate);
        $topProducts = $this->calculateKPI->execute($tenantId, 'top_products', 'monthly', $startDate, $endDate);

        $recentOrders = $this->listOrders->execute(['limit' => self::RECENT_ORDERS_LIMIT], $tenantId)['orders'];

        return new DashboardStatsData(
            totalRevenueCents: $revenue->amount,
            currency: $revenue->unit,
            totalOrders: $orders->amount,
            averageOrderValueCents: $averageOrderValue->amount,
            totalCustomers: $customers->amount,
            conversionRatePercent: $conversion->amount / 100.0,
            activeLoyaltyAccounts: $loyaltyAccounts->amount,
            topProducts: $topProducts->metadata['products'] ?? [],
            recentOrders: $recentOrders,
        );
    }
}
