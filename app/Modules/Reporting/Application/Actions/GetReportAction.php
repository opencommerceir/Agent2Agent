<?php

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Reporting\Application\DTOs\ReportData;
use App\Modules\Reporting\Domain\Exceptions\ReportNotFoundException;
use App\Modules\Reporting\Domain\Repositories\ReportRepositoryInterface;

/**
 * Not wired to MCP this stage — no `report.definition.get` capability
 * was among the 5 requested (only the 5 Generate* capabilities were).
 * Exercised directly, same "built, tested, not yet exposed to Agents"
 * shape Loyalty's `ExpirePointsAction`/Finance's `UpdateTaxRateAction`
 * already carry (ReportingCapabilities' own docblock).
 */
final class GetReportAction
{
    public function __construct(
        private readonly ReportRepositoryInterface $reports,
    ) {
    }

    public function execute(int $reportId, int $tenantId): ReportData
    {
        $report = $this->reports->findById($reportId, $tenantId);

        if (! $report) {
            throw new ReportNotFoundException("Report [{$reportId}] does not exist.");
        }

        $result = $this->reports->findLatestResult($reportId, $tenantId);

        return ReportData::fromEntity($report, $result);
    }
}
