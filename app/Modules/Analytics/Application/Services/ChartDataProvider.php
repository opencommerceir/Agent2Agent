<?php

namespace App\Modules\Analytics\Application\Services;

use App\Modules\Analytics\Application\DTOs\ChartData;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use App\Modules\Reporting\Infrastructure\Queries\SalesQueryBuilder;
use DateTimeImmutable;

/**
 * Shapes already-aggregated data into the `{labels, data}` pair Chart.js
 * wants — reuses Reporting's own `SalesQueryBuilder::byDay()`/`ordersByDay()`
 * (the same "reuse Reporting's Query Builders, don't re-aggregate"
 * decision `CalculateKPIAction`'s own docblock explains) rather than
 * fetching anything itself beyond that. Every day in the requested window
 * gets an explicit 0 entry when no Orders happened that day — a day
 * missing from the *source* data becomes a real gap in the chart
 * otherwise, not an intentional "nothing happened" zero.
 */
final class ChartDataProvider
{
    public function __construct(
        private readonly SalesQueryBuilder $salesQuery,
    ) {
    }

    public function revenueChart(int $tenantId, int $days): ChartData
    {
        [$start, $end] = $this->trailingWindow($days);
        $byDay = $this->salesQuery->byDay($tenantId, DateRange::fromStrings($start->format('Y-m-d'), $end->format('Y-m-d')));

        return $this->fillDays($start, $end, $byDay);
    }

    public function ordersChart(int $tenantId, int $days): ChartData
    {
        [$start, $end] = $this->trailingWindow($days);
        $byDay = $this->salesQuery->ordersByDay($tenantId, DateRange::fromStrings($start->format('Y-m-d'), $end->format('Y-m-d')));

        return $this->fillDays($start, $end, $byDay);
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function trailingWindow(int $days): array
    {
        $end = new DateTimeImmutable('today');
        $start = $end->modify('-'.($days - 1).' days');

        return [$start, $end];
    }

    /**
     * @param array<string, int> $byDay
     */
    private function fillDays(DateTimeImmutable $start, DateTimeImmutable $end, array $byDay): ChartData
    {
        $labels = [];
        $data = [];
        $cursor = $start;

        while ($cursor <= $end) {
            $day = $cursor->format('Y-m-d');
            $labels[] = $day;
            $data[] = $byDay[$day] ?? 0;
            $cursor = $cursor->modify('+1 day');
        }

        return new ChartData($labels, $data);
    }
}
