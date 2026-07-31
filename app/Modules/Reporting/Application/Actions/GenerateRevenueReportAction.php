<?php

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Reporting\Application\DTOs\RevenueReportData;
use App\Modules\Reporting\Domain\Entities\Report;
use App\Modules\Reporting\Domain\Entities\ReportResult;
use App\Modules\Reporting\Domain\Repositories\ReportRepositoryInterface;
use App\Modules\Reporting\Domain\Services\RevenueReportGenerator;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use App\Modules\Reporting\Domain\ValueObjects\ReportFilter;
use App\Modules\Reporting\Domain\ValueObjects\ReportType;
use App\Modules\Reporting\Infrastructure\Queries\RevenueQueryBuilder;

final class GenerateRevenueReportAction
{
    public function __construct(
        private readonly RevenueQueryBuilder $query,
        private readonly RevenueReportGenerator $generator,
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

        $data = RevenueReportData::fromArray(
            $this->generator->generate($totals['gross_revenue'], $totals['tax_collected'], $totals['discounts_applied']),
        );

        $report = Report::create($tenantId, 'Revenue Report', ReportType::Revenue, $dateRange, ReportFilter::empty(), $agentId);
        $report = $this->reports->save($report);
        $this->reports->saveResult(ReportResult::generate($report->id(), $tenantId, $data->toArray()));

        return ['report' => $data->toArray()];
    }
}
