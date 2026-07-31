<?php

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Reporting\Application\DTOs\SalesReportData;
use App\Modules\Reporting\Domain\Entities\Report;
use App\Modules\Reporting\Domain\Entities\ReportResult;
use App\Modules\Reporting\Domain\Repositories\ReportRepositoryInterface;
use App\Modules\Reporting\Domain\Services\SalesReportGenerator;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use App\Modules\Reporting\Domain\ValueObjects\ReportFilter;
use App\Modules\Reporting\Domain\ValueObjects\ReportType;
use App\Modules\Reporting\Infrastructure\Queries\SalesQueryBuilder;

/**
 * One Action = one business operation: compute a Sales Report for a
 * date range and save both the Report definition and its first
 * ReportResult (so it can be retrieved later via GetReportAction) —
 * every Generate*ReportAction in this module follows this same
 * "compute, then persist both rows, then return the computed data"
 * shape. `SalesQueryBuilder` does the SUM/COUNT/GROUP BY aggregation in
 * SQL; `SalesReportGenerator` (pure) only computes `averageOrderValue`.
 */
final class GenerateSalesReportAction
{
    public function __construct(
        private readonly SalesQueryBuilder $query,
        private readonly SalesReportGenerator $generator,
        private readonly ReportRepositoryInterface $reports,
    ) {
    }

    /**
     * @return array{report: array<string, mixed>}
     */
    public function execute(int $tenantId, int $agentId, string $startDate, string $endDate): array
    {
        $dateRange = DateRange::fromStrings($startDate, $endDate);

        $totals = $this->query->totals($tenantId, $dateRange);
        $byDay = $this->query->byDay($tenantId, $dateRange);

        $data = SalesReportData::fromArray(
            $this->generator->generate($totals['total_sales'], $totals['total_orders'], $byDay),
        );

        $report = Report::create($tenantId, 'Sales Report', ReportType::Sales, $dateRange, ReportFilter::empty(), $agentId);
        $report = $this->reports->save($report);
        $this->reports->saveResult(ReportResult::generate($report->id(), $tenantId, $data->toArray()));

        return ['report' => $data->toArray()];
    }
}
