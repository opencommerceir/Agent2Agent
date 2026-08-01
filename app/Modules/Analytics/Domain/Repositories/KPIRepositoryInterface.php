<?php

namespace App\Modules\Analytics\Domain\Repositories;

use App\Modules\Analytics\Domain\Entities\KPI;
use App\Modules\Analytics\Domain\Entities\KPIValue;
use App\Modules\Analytics\Domain\ValueObjects\KPIType;

/**
 * Contract owned by the Domain layer. Also owns `KPIValue` persistence
 * (`saveValue()`/`listValues()`) — the same "repo owns its child records"
 * shape `WorkflowRepositoryInterface`/`LoyaltyAccountRepositoryInterface`
 * already establish for `WorkflowLog`/`Redemption`, rather than a
 * dedicated 3rd Repository interface for a row with no independent
 * identity concerns beyond its own.
 */
interface KPIRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?KPI;

    public function findByType(int $tenantId, KPIType $type): ?KPI;

    /**
     * @return list<KPI>
     */
    public function listByTenant(int $tenantId, ?bool $isActive): array;

    public function save(KPI $kpi): KPI;

    public function saveValue(KPIValue $value): KPIValue;

    /**
     * @return list<KPIValue>
     */
    public function listValues(int $kpiId, int $tenantId, int $limit): array;
}
