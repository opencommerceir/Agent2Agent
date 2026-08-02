<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Warehouse;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseCode;

interface WarehouseRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Warehouse;

    public function codeExists(WarehouseCode $code, int $tenantId): bool;

    /**
     * @return list<Warehouse>
     */
    public function listByTenant(int $tenantId, ?bool $isActive = null): array;

    public function save(Warehouse $warehouse): Warehouse;
}
