<?php

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Reporting\Application\DTOs\TopCustomersReportData;
use App\Modules\Reporting\Domain\Entities\Report;
use App\Modules\Reporting\Domain\Entities\ReportResult;
use App\Modules\Reporting\Domain\Repositories\ReportRepositoryInterface;
use App\Modules\Reporting\Domain\Services\TopCustomersReportGenerator;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use App\Modules\Reporting\Domain\ValueObjects\ReportFilter;
use App\Modules\Reporting\Domain\ValueObjects\ReportType;
use App\Modules\Reporting\Infrastructure\Queries\TopCustomersQueryBuilder;

final class GenerateTopCustomersReportAction
{
    private const DEFAULT_LIMIT = 10;

    public function __construct(
        private readonly TopCustomersQueryBuilder $query,
        private readonly TopCustomersReportGenerator $generator,
        private readonly CustomerRepositoryInterface $customers,
        private readonly ReportRepositoryInterface $reports,
    ) {
    }

    /**
     * @return array{report: array<string, mixed>}
     */
    public function execute(int $tenantId, int $agentId, string $startDate, string $endDate, ?int $limit = null): array
    {
        $limit ??= self::DEFAULT_LIMIT;
        $dateRange = DateRange::fromStrings($startDate, $endDate);

        $rows = $this->query->top($tenantId, $dateRange, $limit);

        $names = [];

        foreach ($rows as $row) {
            $customer = $this->customers->findById($row['customer_id'], $tenantId);
            $names[$row['customer_id']] = $customer?->fullName() ?? "Customer #{$row['customer_id']}";
        }

        $data = TopCustomersReportData::fromArray($this->generator->generate($rows, $names));

        $report = Report::create(
            $tenantId,
            'Top Customers Report',
            ReportType::TopCustomers,
            $dateRange,
            ReportFilter::fromArray(['limit' => $limit]),
            $agentId,
        );
        $report = $this->reports->save($report);
        $this->reports->saveResult(ReportResult::generate($report->id(), $tenantId, $data->toArray()));

        return ['report' => $data->toArray()];
    }
}
