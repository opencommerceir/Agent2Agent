<?php

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Reporting\Application\DTOs\ReportData;
use App\Modules\Reporting\Domain\Repositories\ReportRepositoryInterface;
use App\Modules\Reporting\Domain\ValueObjects\ReportType;

/**
 * Not wired to MCP this stage — see GetReportAction's own docblock.
 * Lists Report *definitions* only (metadata: type, date range, who ran
 * it) — deliberately does not also fetch/attach each one's ReportResult
 * (that would be N extra queries for a list endpoint whose whole point
 * is a lightweight overview; GetReportAction is the one that resolves a
 * single Report's latest result).
 */
final class ListReportsAction
{
    private const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly ReportRepositoryInterface $reports,
    ) {
    }

    /**
     * @param array{report_type?: string, limit?: int} $input
     * @return array{reports: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $reportType = isset($input['report_type']) ? ReportType::from($input['report_type']) : null;
        $limit = isset($input['limit']) ? (int) $input['limit'] : self::DEFAULT_LIMIT;

        $reports = $this->reports->list($tenantId, $reportType, $limit);

        return [
            'reports' => array_map(fn ($report) => ReportData::fromEntity($report, null)->toArray(), $reports),
        ];
    }
}
