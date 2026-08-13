<?php

namespace App\Domains\Nexus\Credit\Application\Actions;

use App\Domains\Nexus\Credit\Application\DTOs\HoldingCreditPoolData;
use App\Domains\Nexus\Credit\Domain\Entities\HoldingCreditPool;
use App\Domains\Nexus\Credit\Domain\Entities\HoldingCreditPoolTransaction;
use App\Domains\Nexus\Credit\Domain\Repositories\HoldingCreditPoolRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\HoldingCreditPoolTransactionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;

/**
 * The pool's own CostGate write path — called by SpendCreditsForActionAction
 * instead of DeductCreditsAction when the acting Business belongs to a
 * Holding with pooling enabled. A pool that has never received a
 * contribution has no row yet; opening one on first use here (same pattern
 * GrantCreditsAction already uses for CreditBalance) lets debit() throw its
 * own InsufficientCreditException against a real balance of 0 rather than
 * needing a separate "no pool yet" error — HoldingCreditPool::debit()
 * throws before any mutation, so a failed first attempt never creates an
 * orphan zero-balance row.
 */
final class DeductFromHoldingPoolAction
{
    public function __construct(
        private readonly HoldingCreditPoolRepositoryInterface $pools,
        private readonly HoldingCreditPoolTransactionRepositoryInterface $poolTransactions,
    ) {
    }

    public function execute(int $holdingId, int $amount, string $reason, int $actingBusinessId, ?int $relatedId = null): HoldingCreditPoolData
    {
        $pool = $this->pools->findByHoldingId($holdingId) ?? HoldingCreditPool::open($holdingId);
        $pool->debit($amount);
        $pool = $this->pools->save($pool);

        $this->poolTransactions->save(HoldingCreditPoolTransaction::record(
            holdingId: $holdingId,
            businessId: $actingBusinessId,
            type: CreditTransactionType::PoolDeduction,
            amount: $amount,
            reason: $reason,
            balanceAfter: $pool->balance(),
            relatedId: $relatedId,
        ));

        return HoldingCreditPoolData::fromEntity($pool);
    }
}
