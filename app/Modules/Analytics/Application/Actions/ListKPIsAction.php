<?php

namespace App\Modules\Analytics\Application\Actions;

use App\Modules\Analytics\Application\DTOs\KPIData;
use App\Modules\Analytics\Domain\Entities\KPI;
use App\Modules\Analytics\Domain\Repositories\KPIRepositoryInterface;

final class ListKPIsAction
{
    public function __construct(
        private readonly KPIRepositoryInterface $kpis,
    ) {
    }

    /**
     * @return list<KPIData>
     */
    public function execute(int $tenantId, ?bool $isActive): array
    {
        return array_map(
            fn (KPI $kpi) => KPIData::fromEntity($kpi),
            $this->kpis->listByTenant($tenantId, $isActive),
        );
    }
}
