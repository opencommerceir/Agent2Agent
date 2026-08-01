<?php

namespace App\Modules\Analytics\Domain\Repositories;

use App\Modules\Analytics\Domain\Entities\AnalyticsSnapshot;
use DateTimeImmutable;

interface AnalyticsSnapshotRepositoryInterface
{
    public function findByDate(int $tenantId, DateTimeImmutable $date): ?AnalyticsSnapshot;

    /**
     * Most-recent-first, for the Dashboard's own Revenue/Orders charts.
     *
     * @return list<AnalyticsSnapshot>
     */
    public function listByTenant(int $tenantId, int $limit): array;

    /**
     * Upserts by (tenant_id, snapshot_date) — the scheduled command runs
     * daily, but re-running it for a date that already has a Snapshot
     * (a manual re-trigger, a retried failed run) replaces that day's row
     * rather than accumulating duplicates.
     */
    public function save(AnalyticsSnapshot $snapshot): AnalyticsSnapshot;
}
