<?php

namespace App\Modules\Reporting\Domain\Repositories;

use App\Modules\Reporting\Domain\Entities\Report;
use App\Modules\Reporting\Domain\Entities\ReportResult;
use App\Modules\Reporting\Domain\ValueObjects\ReportType;

/**
 * Contract owned by the Domain layer (Interfaces Over Tight Coupling).
 * Every method takes tenantId explicitly — never inferred from ambient
 * state. Also owns ReportResult persistence (saveResult()/
 * findLatestResult()) — a result has no meaning detached from the
 * Report it's a result *of*, the same "repository interface owns its
 * child records" shape Workflows' WorkflowRepositoryInterface (owns
 * WorkflowLog) and Loyalty's LoyaltyAccountRepositoryInterface (owns
 * Redemption) already established.
 *
 * Deliberately NOT the interface the report-generating Actions use to
 * read Commerce/Loyalty data for computing a report's numbers — that's
 * the Query Builders' job (Infrastructure/Queries), a separate, explicit
 * exception to this same Dependency Inversion pattern for read-only
 * cross-module aggregation (see SalesQueryBuilder's own docblock for the
 * full reasoning). This interface only ever persists/retrieves
 * Reporting's *own* two tables.
 */
interface ReportRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Report;

    /**
     * @return list<Report>
     */
    public function list(int $tenantId, ?ReportType $reportType, int $limit): array;

    public function save(Report $report): Report;

    public function saveResult(ReportResult $result): ReportResult;

    public function findLatestResult(int $reportId, int $tenantId): ?ReportResult;
}
