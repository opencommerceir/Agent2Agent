<?php

namespace App\Domains\Nexus\Credit\Domain\Repositories;

use App\Domains\Nexus\Credit\Domain\Entities\CreditTransaction;

/**
 * Contract owned by the Domain layer. Infrastructure provides the
 * implementation (Interfaces Over Tight Coupling). No update()/delete() —
 * the ledger is append-only (CreditTransaction's own docblock).
 */
interface CreditTransactionRepositoryInterface
{
    /**
     * @return list<CreditTransaction>
     */
    public function findByBusinessId(int $businessId): array;

    public function save(CreditTransaction $transaction): CreditTransaction;
}
