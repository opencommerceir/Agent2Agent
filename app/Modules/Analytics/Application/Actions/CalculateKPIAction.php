<?php

namespace App\Modules\Analytics\Application\Actions;

use App\Modules\Analytics\Application\DTOs\KPIValueData;
use App\Modules\Analytics\Domain\Entities\KPI;
use App\Modules\Analytics\Domain\Entities\KPIValue;
use App\Modules\Analytics\Domain\Repositories\KPIRepositoryInterface;
use App\Modules\Analytics\Domain\Services\ConversionRateCalculator;
use App\Modules\Analytics\Domain\Services\CustomerCalculator;
use App\Modules\Analytics\Domain\Services\OrderCalculator;
use App\Modules\Analytics\Domain\Services\RevenueCalculator;
use App\Modules\Analytics\Domain\ValueObjects\KPIType;
use App\Modules\Analytics\Domain\ValueObjects\Money;
use App\Modules\Analytics\Domain\ValueObjects\TimePeriod;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use App\Modules\Reporting\Infrastructure\Queries\LoyaltyQueryBuilder;
use App\Modules\Reporting\Infrastructure\Queries\RevenueQueryBuilder;
use App\Modules\Reporting\Infrastructure\Queries\SalesQueryBuilder;
use App\Modules\Reporting\Infrastructure\Queries\TopCustomersQueryBuilder;
use App\Modules\Reporting\Infrastructure\Queries\TopProductsQueryBuilder;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The one entry point every KPIType is computed through — MCP's own
 * `analytics.kpi.calculate` handler, the Dashboard's `GetDashboardStatsAction`,
 * and the daily snapshot command (`GenerateSnapshotAction`) all funnel
 * through this same Action.
 *
 * **Reuses Reporting's own Query Builders directly** (`SalesQueryBuilder`/
 * `RevenueQueryBuilder`/`TopProductsQueryBuilder`/`TopCustomersQueryBuilder`/
 * `LoyaltyQueryBuilder`, plus Reporting's own `DateRange` VO) for every KPI
 * Reporting already knows how to aggregate (Revenue, Total Orders, Top
 * Products, Loyalty points/accounts) — rather than re-implementing the
 * identical SUM/COUNT/GROUP BY SQL a second time, which would create two
 * independent, potentially-diverging sources of truth for the same number
 * (confirmed as the wrong direction before writing any of this module).
 * Deliberately calls the Query Builders themselves, not Reporting's own
 * `Generate*ReportAction`s — those Actions have a real side effect
 * (persisting a `Report`+`ReportResult` row per call, appropriate for an
 * Agent explicitly asking to run a report), which would spam the
 * `reports` table on every cache-miss KPI read. This is a second, narrower
 * application of the exact CQRS Read-Model exception `SalesQueryBuilder`'s
 * own docblock already established for Reporting itself — the coupling is
 * still explicit and contained (only this one Action reaches into
 * Reporting's `Infrastructure\Queries\*`).
 *
 * KPITypes with no Reporting equivalent (`ConversionRate`,
 * `RevenueGrowthRate`, `TotalCustomers`, `NewCustomers`,
 * `CustomerRetentionRate`, `CustomerLifetimeValue`, `LowStockProducts`)
 * are genuinely new — computed from Commerce's own Repository Interfaces
 * (`CustomerRepositoryInterface`, the new `CartRepositoryInterface::countCreatedBetween()`/
 * `InventoryRepositoryInterface::listLowStock()`) and the 4 pure Domain
 * Calculators this stage built.
 *
 * Result is cached for 1 hour (`Cache::remember`, keyed by
 * tenant+type+date-range) — a KPI is a frequent, repeatedly-read
 * aggregate, not a one-shot Agent request. A `KPIValue` row is persisted
 * only on an actual cache miss (a fresh computation), never on a cache
 * hit — the point of caching would be defeated by writing a new,
 * identical row on every read regardless.
 *
 * See `KPIValueData`'s own docblock for how `unit` ("value_currency")
 * doubles as a scale tag for non-monetary KPIs.
 */
final class CalculateKPIAction
{
    private const CACHE_TTL_SECONDS = 3600;

    private const LOW_STOCK_THRESHOLD = 10;

    private const TOP_PRODUCTS_LIMIT = 5;

    private const DEFAULT_CURRENCY = 'USD';

    /**
     * A practical "give me effectively everything" bound for
     * Customer/TopCustomers listings — large enough for any real tenant
     * at this platform's current scale, safer than a literal PHP_INT_MAX
     * as a SQL LIMIT value across database drivers.
     */
    private const EFFECTIVELY_ALL = 100_000;

    public function __construct(
        private readonly KPIRepositoryInterface $kpis,
        private readonly SalesQueryBuilder $salesQuery,
        private readonly RevenueQueryBuilder $revenueQuery,
        private readonly TopProductsQueryBuilder $topProductsQuery,
        private readonly TopCustomersQueryBuilder $topCustomersQuery,
        private readonly LoyaltyQueryBuilder $loyaltyQuery,
        private readonly CustomerRepositoryInterface $customers,
        private readonly CartRepositoryInterface $carts,
        private readonly InventoryRepositoryInterface $inventories,
        private readonly ProductRepositoryInterface $products,
        private readonly RevenueCalculator $revenueCalculator,
        private readonly OrderCalculator $orderCalculator,
        private readonly CustomerCalculator $customerCalculator,
        private readonly ConversionRateCalculator $conversionRateCalculator,
    ) {
    }

    public function execute(int $tenantId, string $kpiType, string $timePeriod, string $startDate, string $endDate): KPIValueData
    {
        $type = KPIType::from($kpiType);
        $period = TimePeriod::from($timePeriod);
        $dateRange = DateRange::fromStrings($startDate, $endDate);

        $cacheKey = sprintf('analytics.kpi.%d.%s.%s.%s', $tenantId, $type->value, $dateRange->startDate(), $dateRange->endDate());

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn () => $this->calculateAndPersist($tenantId, $type, $period, $dateRange),
        );
    }

    private function calculateAndPersist(int $tenantId, KPIType $type, TimePeriod $period, DateRange $dateRange): KPIValueData
    {
        [$amount, $unit, $metadata] = match ($type) {
            KPIType::Revenue => $this->calculateRevenue($tenantId, $dateRange),
            KPIType::RevenueGrowthRate => $this->calculateRevenueGrowthRate($tenantId, $dateRange),
            KPIType::TotalOrders => $this->calculateTotalOrders($tenantId, $dateRange),
            KPIType::AverageOrderValue => $this->calculateAverageOrderValue($tenantId, $dateRange),
            KPIType::TotalCustomers => $this->calculateTotalCustomers($tenantId),
            KPIType::NewCustomers => $this->calculateNewCustomers($tenantId, $dateRange),
            KPIType::ConversionRate => $this->calculateConversionRate($tenantId, $dateRange),
            KPIType::TopProducts => $this->calculateTopProducts($tenantId, $dateRange),
            KPIType::LowStockProducts => $this->calculateLowStockProducts($tenantId),
            KPIType::LoyaltyPointsEarned => $this->calculateLoyalty($tenantId, $dateRange, 'total_points_earned'),
            KPIType::LoyaltyPointsRedeemed => $this->calculateLoyalty($tenantId, $dateRange, 'total_points_redeemed'),
            KPIType::ActiveLoyaltyAccounts => $this->calculateLoyalty($tenantId, $dateRange, 'active_accounts'),
            KPIType::CustomerRetentionRate => $this->calculateRetentionRate($tenantId, $dateRange),
            KPIType::CustomerLifetimeValue => $this->calculateLifetimeValue($tenantId, $dateRange),
        };

        $kpi = $this->kpis->findByType($tenantId, $type) ?? $this->kpis->save(KPI::define($tenantId, $type, $this->defaultName($type)));

        $value = KPIValue::record(
            tenantId: $tenantId,
            kpiId: $kpi->id(),
            value: Money::fromAmount($amount, $unit),
            timePeriod: $period,
            periodStart: $dateRange->start(),
            periodEnd: $dateRange->end(),
            metadata: $metadata,
        );

        $value = $this->kpis->saveValue($value);

        return KPIValueData::fromEntity($value, $type->value);
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateRevenue(int $tenantId, DateRange $dateRange): array
    {
        $totals = $this->revenueQuery->totals($tenantId, $dateRange);
        $result = $this->revenueCalculator->calculate(['metric' => 'total', 'grossRevenueCents' => $totals['gross_revenue']]);

        return [$result['amountCents'], self::DEFAULT_CURRENCY, $totals];
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateRevenueGrowthRate(int $tenantId, DateRange $dateRange): array
    {
        $current = $this->revenueQuery->totals($tenantId, $dateRange);
        $previousRange = $this->previousPeriod($dateRange);
        $previous = $this->revenueQuery->totals($tenantId, $previousRange);

        $result = $this->revenueCalculator->calculate([
            'metric' => 'growth_rate',
            'currentPeriodCents' => $current['gross_revenue'],
            'previousPeriodCents' => $previous['gross_revenue'],
        ]);

        $percent = $result['growthRatePercent'];

        return [(int) round(($percent ?? 0.0) * 100), 'PCT', ['growthRatePercent' => $percent]];
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateTotalOrders(int $tenantId, DateRange $dateRange): array
    {
        $totals = $this->salesQuery->totals($tenantId, $dateRange);
        $result = $this->orderCalculator->calculate(['metric' => 'total', 'totalOrders' => $totals['total_orders']]);

        return [$result['count'], 'CNT', []];
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateAverageOrderValue(int $tenantId, DateRange $dateRange): array
    {
        $totals = $this->salesQuery->totals($tenantId, $dateRange);
        $result = $this->orderCalculator->calculate([
            'metric' => 'average_order_value',
            'totalRevenueCents' => $totals['total_sales'],
            'totalOrders' => $totals['total_orders'],
        ]);

        return [$result['amountCents'], self::DEFAULT_CURRENCY, $totals];
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateTotalCustomers(int $tenantId): array
    {
        $createdAt = $this->customerCreatedTimestamps($tenantId);
        $result = $this->customerCalculator->calculate(['metric' => 'total', 'customerCreatedAt' => $createdAt]);

        return [$result['count'], 'CNT', []];
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateNewCustomers(int $tenantId, DateRange $dateRange): array
    {
        $createdAt = $this->customerCreatedTimestamps($tenantId);
        $result = $this->customerCalculator->calculate([
            'metric' => 'new',
            'customerCreatedAt' => $createdAt,
            'periodStart' => $dateRange->start(),
            'periodEnd' => $dateRange->end(),
        ]);

        return [$result['count'], 'CNT', []];
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateConversionRate(int $tenantId, DateRange $dateRange): array
    {
        $totals = $this->salesQuery->totals($tenantId, $dateRange);
        $totalCarts = $this->carts->countCreatedBetween($tenantId, $dateRange->start(), $dateRange->end());

        $result = $this->conversionRateCalculator->calculate(['totalCarts' => $totalCarts, 'totalOrders' => $totals['total_orders']]);

        return [(int) round($result['conversionRatePercent'] * 100), 'PCT', ['totalCarts' => $totalCarts]];
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateTopProducts(int $tenantId, DateRange $dateRange): array
    {
        $rows = $this->topProductsQuery->top($tenantId, $dateRange, self::TOP_PRODUCTS_LIMIT);

        $products = array_map(function (array $row) use ($tenantId) {
            $product = $this->products->findById($row['product_id'], $tenantId);

            return [
                'productId' => $row['product_id'],
                'name' => $product?->name(),
                'quantitySold' => $row['quantity_sold'],
                'totalRevenueCents' => $row['total_revenue'],
            ];
        }, $rows);

        return [0, 'LST', ['products' => $products]];
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateLowStockProducts(int $tenantId): array
    {
        $items = $this->inventories->listLowStock($tenantId, self::LOW_STOCK_THRESHOLD);

        $products = array_map(fn ($inventory) => [
            'productId' => $inventory->productId(),
            'available' => $inventory->available(),
        ], $items);

        return [count($items), 'LST', ['products' => $products]];
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateLoyalty(int $tenantId, DateRange $dateRange, string $field): array
    {
        $totals = $this->loyaltyQuery->totals($tenantId, $dateRange);
        $unit = $field === 'active_accounts' ? 'CNT' : 'PTS';

        return [$totals[$field], $unit, $totals];
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateRetentionRate(int $tenantId, DateRange $dateRange): array
    {
        $customers = $this->topCustomersQuery->top($tenantId, $dateRange, self::EFFECTIVELY_ALL);
        $repeatCustomers = count(array_filter($customers, fn (array $c) => $c['total_orders'] > 1));

        $result = $this->customerCalculator->calculate([
            'metric' => 'retention_rate',
            'repeatCustomers' => $repeatCustomers,
            'totalCustomers' => count($customers),
        ]);

        return [(int) round($result['retentionRatePercent'] * 100), 'PCT', ['repeatCustomers' => $repeatCustomers, 'totalOrderingCustomers' => count($customers)]];
    }

    /** @return array{0: int, 1: string, 2: array<string, mixed>} */
    private function calculateLifetimeValue(int $tenantId, DateRange $dateRange): array
    {
        $customers = $this->topCustomersQuery->top($tenantId, $dateRange, self::EFFECTIVELY_ALL);
        $revenue = $this->revenueQuery->totals($tenantId, $dateRange);

        $result = $this->customerCalculator->calculate([
            'metric' => 'lifetime_value',
            'totalRevenueCents' => $revenue['gross_revenue'],
            'totalCustomers' => count($customers),
        ]);

        return [$result['amountCents'], self::DEFAULT_CURRENCY, ['orderingCustomers' => count($customers)]];
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private function customerCreatedTimestamps(int $tenantId): array
    {
        return array_map(
            fn ($customer) => $customer->createdAt(),
            $this->customers->listByTenant($tenantId, null, self::EFFECTIVELY_ALL),
        );
    }

    private function previousPeriod(DateRange $dateRange): DateRange
    {
        $durationSeconds = $dateRange->end()->getTimestamp() - $dateRange->start()->getTimestamp();
        $previousEnd = $dateRange->start()->modify('-1 second');
        $previousStart = $previousEnd->setTimestamp($previousEnd->getTimestamp() - $durationSeconds);

        return DateRange::fromStrings($previousStart->format('Y-m-d'), $previousEnd->format('Y-m-d'));
    }

    private function defaultName(KPIType $type): string
    {
        return ucwords(str_replace('_', ' ', $type->value));
    }
}
