<?php

namespace App\Domains\Nexus\Business\Domain\Repositories;

use App\Domains\Nexus\Business\Domain\Entities\Business;

/**
 * Contract owned by the Domain layer. Infrastructure provides the
 * implementation (Interfaces Over Tight Coupling).
 */
interface BusinessRepositoryInterface
{
    public function findById(int $id): ?Business;

    public function findByTenantId(int $tenantId): ?Business;

    public function save(Business $business): Business;
}
