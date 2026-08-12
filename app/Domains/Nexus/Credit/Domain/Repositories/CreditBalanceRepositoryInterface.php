<?php

namespace App\Domains\Nexus\Credit\Domain\Repositories;

use App\Domains\Nexus\Credit\Domain\Entities\CreditBalance;

/**
 * Contract owned by the Domain layer. Infrastructure provides the
 * implementation (Interfaces Over Tight Coupling).
 */
interface CreditBalanceRepositoryInterface
{
    public function findByBusinessId(int $businessId): ?CreditBalance;

    public function save(CreditBalance $balance): CreditBalance;
}
