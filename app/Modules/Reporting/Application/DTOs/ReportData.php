<?php

namespace App\Modules\Reporting\Application\DTOs;

use App\Modules\Reporting\Domain\Entities\Report;
use App\Modules\Reporting\Domain\Entities\ReportResult;

/**
 * Structured data transfer for a *saved* Report (backs GetReportAction/
 * ListReportsAction — the two Actions not wired to MCP this stage, see
 * ReportingCapabilities' own docblock) — distinct from the 5
 * report-type-specific DTOs (SalesReportData, etc.), which represent a
 * freshly computed report's numbers, not the saved record of having run
 * one. `resultData`/`generatedAt` are null when no ReportResult exists
 * yet for this Report (shouldn't happen in practice — every Report this
 * stage creates is saved together with its first ReportResult in the
 * same Action — but GetReportAction still resolves the pairing
 * defensively rather than assuming one always exists).
 */
final class ReportData
{
    /**
     * @param array<string, mixed> $filters
     * @param ?array<string, mixed> $resultData
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $reportType,
        public readonly string $dateRangeStart,
        public readonly string $dateRangeEnd,
        public readonly array $filters,
        public readonly int $createdBy,
        public readonly ?array $resultData,
        public readonly ?string $generatedAt,
    ) {
    }

    public static function fromEntity(Report $report, ?ReportResult $result): self
    {
        return new self(
            id: $report->id(),
            tenantId: $report->tenantId(),
            name: $report->name(),
            reportType: $report->reportType()->value,
            dateRangeStart: $report->dateRange()->startDate(),
            dateRangeEnd: $report->dateRange()->endDate(),
            filters: $report->filters()->toArray(),
            createdBy: $report->createdBy(),
            resultData: $result?->resultData(),
            generatedAt: $result?->generatedAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'name' => $this->name,
            'reportType' => $this->reportType,
            'dateRangeStart' => $this->dateRangeStart,
            'dateRangeEnd' => $this->dateRangeEnd,
            'filters' => $this->filters,
            'createdBy' => $this->createdBy,
            'resultData' => $this->resultData,
            'generatedAt' => $this->generatedAt,
        ];
    }
}
