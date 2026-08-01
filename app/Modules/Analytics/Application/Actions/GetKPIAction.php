<?php

namespace App\Modules\Analytics\Application\Actions;

use App\Modules\Analytics\Application\DTOs\KPIData;
use App\Modules\Analytics\Domain\Exceptions\KPINotFoundException;
use App\Modules\Analytics\Domain\Repositories\KPIRepositoryInterface;

final class GetKPIAction
{
    public function __construct(
        private readonly KPIRepositoryInterface $kpis,
    ) {
    }

    public function execute(int $id, int $tenantId): KPIData
    {
        $kpi = $this->kpis->findById($id, $tenantId);

        if (! $kpi) {
            throw new KPINotFoundException("KPI [{$id}] does not exist.");
        }

        return KPIData::fromEntity($kpi);
    }
}
