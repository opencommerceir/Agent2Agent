<?php

namespace App\Modules\Finance\Domain\Repositories;

use App\Modules\Finance\Domain\Entities\TaxRate;
use App\Modules\Finance\Domain\ValueObjects\TaxRegion;

/**
 * Contract owned by the Domain layer (Interfaces Over Tight Coupling).
 * Every method takes tenantId explicitly — never inferred from ambient
 * state.
 */
interface TaxRateRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?TaxRate;

    public function findByRegion(TaxRegion $region, int $tenantId): ?TaxRate;

    public function regionExists(TaxRegion $region, int $tenantId): bool;

    /**
     * @return list<TaxRate>
     */
    public function list(int $tenantId, ?bool $isActive): array;

    public function save(TaxRate $taxRate): TaxRate;
}
