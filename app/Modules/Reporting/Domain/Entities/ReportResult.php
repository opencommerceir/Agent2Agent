<?php

namespace App\Modules\Reporting\Domain\Entities;

use DateTimeImmutable;

/**
 * The computed output of running a Report, at a point in time — kept
 * separate from `Report` itself (same "parent definition, child result"
 * split Workflows' `Workflow`/`WorkflowLog` establish) because a Report
 * can be re-run — each run writes a new, immutable ReportResult rather
 * than overwriting the previous one, so `GetReportAction` always
 * resolves "the latest result for this Report" rather than there being
 * only ever one.
 *
 * `result_data` is a plain associative array — deliberately the exact
 * shape each report-specific DTO (`SalesReportData`, etc.) already
 * produces via its own `toArray()`, so persisting it and later
 * rehydrating it for `GetReportAction` requires no separate mapping per
 * ReportType.
 */
final class ReportResult
{
    /**
     * @param array<string, mixed> $resultData
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $reportId,
        private readonly int $tenantId,
        private readonly array $resultData,
        private readonly DateTimeImmutable $generatedAt,
        private readonly ?DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * @param array<string, mixed> $resultData
     */
    public static function generate(
        int $reportId,
        int $tenantId,
        array $resultData,
        ?DateTimeImmutable $expiresAt = null,
    ): self {
        return new self(
            id: null,
            reportId: $reportId,
            tenantId: $tenantId,
            resultData: $resultData,
            generatedAt: new DateTimeImmutable(),
            expiresAt: $expiresAt,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function reportId(): int
    {
        return $this->reportId;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    /**
     * @return array<string, mixed>
     */
    public function resultData(): array
    {
        return $this->resultData;
    }

    public function generatedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(DateTimeImmutable $asOf): bool
    {
        return $this->expiresAt !== null && $asOf >= $this->expiresAt;
    }
}
