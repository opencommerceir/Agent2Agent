<?php

namespace App\Modules\Reporting\Domain\Entities;

use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use App\Modules\Reporting\Domain\ValueObjects\ReportFilter;
use App\Modules\Reporting\Domain\ValueObjects\ReportType;
use DateTimeImmutable;

/**
 * The saved *definition* of a report that was run — type, date range,
 * filters, which Agent ran it. Immutable: nothing about a Report is
 * editable after creation (there is no `UpdateReportAction` — a report
 * definition is a historical record of what was asked for, not a
 * configurable, ongoing entity like a Workflow). The actual computed
 * numbers live on a separate `ReportResult` (see that Entity's own
 * docblock for why persistence is split this way).
 */
final class Report
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly string $name,
        private readonly ReportType $reportType,
        private readonly DateRange $dateRange,
        private readonly ReportFilter $filters,
        private readonly int $createdBy,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $tenantId,
        string $name,
        ReportType $reportType,
        DateRange $dateRange,
        ReportFilter $filters,
        int $createdBy,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            name: $name,
            reportType: $reportType,
            dateRange: $dateRange,
            filters: $filters,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function reportType(): ReportType
    {
        return $this->reportType;
    }

    public function dateRange(): DateRange
    {
        return $this->dateRange;
    }

    public function filters(): ReportFilter
    {
        return $this->filters;
    }

    public function createdBy(): int
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
