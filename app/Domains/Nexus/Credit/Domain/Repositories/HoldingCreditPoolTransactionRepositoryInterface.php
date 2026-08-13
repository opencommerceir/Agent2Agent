<?php

namespace App\Domains\Nexus\Credit\Domain\Repositories;

use App\Domains\Nexus\Credit\Domain\Entities\HoldingCreditPoolTransaction;

/**
 * No update()/delete() — the pool ledger is append-only, same as
 * CreditTransactionRepositoryInterface.
 */
interface HoldingCreditPoolTransactionRepositoryInterface
{
    /**
     * @return list<HoldingCreditPoolTransaction>
     */
    public function findByHoldingId(int $holdingId): array;

    public function save(HoldingCreditPoolTransaction $transaction): HoldingCreditPoolTransaction;
}
