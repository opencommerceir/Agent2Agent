<?php

namespace App\Modules\Analytics\Application\Actions;

use App\Modules\Analytics\Application\DTOs\AnalyticsSnapshotData;
use App\Modules\Analytics\Domain\Entities\AnalyticsSnapshot;
use App\Modules\Analytics\Domain\Repositories\AnalyticsSnapshotRepositoryInterface;
use App\Modules\Analytics\Domain\ValueObjects\Money;
use App\Modules\Analytics\Domain\ValueObjects\TimePeriod;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use App\Modules\Reporting\Infrastructure\Queries\TopCustomersQueryBuilder;
use DateTimeImmutable;

/**
 * Backs both the `analytics.snapshot.generate` MCP capability and the
 * daily `analytics:generate-snapshot` scheduled command (§7.18) — one
 * Tenant's headline numbers for one calendar day, computed through the
 * exact same `CalculateKPIAction` every other KPI read uses (so a
 * Snapshot's own `totalRevenue` can never drift from what
 * `analytics.kpi.calculate` would report for the same day).
 */
final class GenerateSnapshotAction
{
    private const TOP_CUSTOMERS_LIMIT = 5;

    public function __construct(
        private readonly CalculateKPIAction $calculateKPI,
        private readonly AnalyticsSnapshotRepositoryInterface $snapshots,
        private readonly TopCustomersQueryBuilder $topCustomersQuery,
    ) {
    }

    public function execute(int $tenantId, ?DateTimeImmutable $date = null): AnalyticsSnapshotData
    {
        $date ??= new DateTimeImmutable('today');
        [$start, $end] = TimePeriod::Daily->boundsFor($date);
        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');

        $revenue = $this->calculateKPI->execute($tenantId, 'revenue', 'daily', $startDate, $endDate);
        $orders = $this->calculateKPI->execute($tenantId, 'total_orders', 'daily', $startDate, $endDate);
        $averageOrderValue = $this->calculateKPI->execute($tenantId, 'average_order_value', 'daily', $startDate, $endDate);
        $customers = $this->calculateKPI->execute($tenantId, 'total_customers', 'daily', $startDate, $endDate);
        $conversion = $this->calculateKPI->execute($tenantId, 'conversion_rate', 'daily', $startDate, $endDate);
        $topProducts = $this->calculateKPI->execute($tenantId, 'top_products', 'daily', $startDate, $endDate);

        $topCustomers = $this->topCustomersQuery->top($tenantId, DateRange::fromStrings($startDate, $endDate), self::TOP_CUSTOMERS_LIMIT);

        $snapshot = AnalyticsSnapshot::capture(
            tenantId: $tenantId,
            snapshotDate: $start,
            totalRevenue: Money::fromAmount($revenue->amount, $revenue->unit),
            totalOrders: $orders->amount,
            totalCustomers: $customers->amount,
            avgOrderValue: Money::fromAmount($averageOrderValue->amount, $averageOrderValue->unit),
            conversionRate: $conversion->amount / 100.0,
            topProducts: $topProducts->metadata['products'] ?? [],
            topCustomers: $topCustomers,
        );

        $snapshot = $this->snapshots->save($snapshot);

        return AnalyticsSnapshotData::fromEntity($snapshot);
    }
}
