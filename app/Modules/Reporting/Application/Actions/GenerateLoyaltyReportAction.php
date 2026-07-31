<?php

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Reporting\Application\DTOs\LoyaltyReportData;
use App\Modules\Reporting\Domain\Entities\Report;
use App\Modules\Reporting\Domain\Entities\ReportResult;
use App\Modules\Reporting\Domain\Repositories\ReportRepositoryInterface;
use App\Modules\Reporting\Domain\Services\LoyaltyReportGenerator;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use App\Modules\Reporting\Domain\ValueObjects\ReportFilter;
use App\Modules\Reporting\Domain\ValueObjects\ReportType;
use App\Modules\Reporting\Infrastructure\Queries\LoyaltyQueryBuilder;

/**
 * `customer_id` for each top earner comes from
 * `LoyaltyQueryBuilder::topEarners()`'s own join against
 * `loyalty_accounts` (SQL, not a Loyalty Repository call) — this Action
 * only needs Commerce's `CustomerRepositoryInterface` to resolve display
 * names, never Loyalty's own Repositories directly.
 */
final class GenerateLoyaltyReportAction
{
    public function __construct(
        private readonly LoyaltyQueryBuilder $query,
        private readonly LoyaltyReportGenerator $generator,
        private readonly CustomerRepositoryInterface $customers,
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
        $topEarnerRows = $this->query->topEarners($tenantId, $dateRange);

        $names = [];

        foreach ($topEarnerRows as $row) {
            $customer = $this->customers->findById($row['customer_id'], $tenantId);
            $names[$row['customer_id']] = $customer?->fullName() ?? "Customer #{$row['customer_id']}";
        }

        $data = LoyaltyReportData::fromArray($this->generator->generate(
            $totals['total_points_earned'],
            $totals['total_points_redeemed'],
            $totals['active_accounts'],
            $topEarnerRows,
            $names,
        ));

        $report = Report::create($tenantId, 'Loyalty Report', ReportType::Loyalty, $dateRange, ReportFilter::empty(), $agentId);
        $report = $this->reports->save($report);
        $this->reports->saveResult(ReportResult::generate($report->id(), $tenantId, $data->toArray()));

        return ['report' => $data->toArray()];
    }
}
