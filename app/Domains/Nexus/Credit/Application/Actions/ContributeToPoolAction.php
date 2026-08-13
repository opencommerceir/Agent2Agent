<?php

namespace App\Domains\Nexus\Credit\Application\Actions;

use App\Domains\Nexus\Credit\Application\DTOs\HoldingCreditPoolData;
use App\Domains\Nexus\Credit\Domain\Entities\HoldingCreditPool;
use App\Domains\Nexus\Credit\Domain\Entities\HoldingCreditPoolTransaction;
use App\Domains\Nexus\Credit\Domain\Repositories\HoldingCreditPoolRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\HoldingCreditPoolTransactionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\ValueObjects\SubsidiaryStatus;
use InvalidArgumentException;

/**
 * Phase 7/M2's "shared credit pool" funding mechanic — an explicit transfer
 * from a member's own balance into the Holding's pool, not automatic
 * revenue-sharing (no such infrastructure exists anywhere in this
 * codebase). Two ledger writes for two different aggregates: the member's
 * own CreditTransaction (via the existing DeductCreditsAction, unchanged)
 * records the debit side, and a HoldingCreditPoolTransaction records the
 * credit side on the pool.
 */
final class ContributeToPoolAction
{
    public function __construct(
        private readonly HoldingRepositoryInterface $holdings,
        private readonly HoldingSubsidiaryRepositoryInterface $subsidiaries,
        private readonly DeductCreditsAction $deductCredits,
        private readonly HoldingCreditPoolRepositoryInterface $pools,
        private readonly HoldingCreditPoolTransactionRepositoryInterface $poolTransactions,
    ) {
    }

    public function execute(int $holdingId, int $contributingBusinessId, int $amount): HoldingCreditPoolData
    {
        $holding = $this->holdings->findById($holdingId);

        if (! $holding) {
            throw new InvalidArgumentException("Holding [{$holdingId}] does not exist.");
        }

        $this->assertMembership($holding->parentBusinessId(), $holdingId, $contributingBusinessId);

        // Debits the contributor's own balance first — InsufficientCreditException
        // propagates unchanged if they can't cover it, same as any other
        // CostGate-shaped deduction, before the pool is ever touched.
        $this->deductCredits->execute($contributingBusinessId, $amount, 'holding.pool.contribution', $holdingId);

        $pool = $this->pools->findByHoldingId($holdingId) ?? HoldingCreditPool::open($holdingId);
        $pool->credit($amount);
        $pool = $this->pools->save($pool);

        $this->poolTransactions->save(HoldingCreditPoolTransaction::record(
            holdingId: $holdingId,
            businessId: $contributingBusinessId,
            type: CreditTransactionType::PoolContribution,
            amount: $amount,
            reason: 'holding.pool.contribution',
            balanceAfter: $pool->balance(),
        ));

        return HoldingCreditPoolData::fromEntity($pool);
    }

    private function assertMembership(int $parentBusinessId, int $holdingId, int $businessId): void
    {
        if ($businessId === $parentBusinessId) {
            return;
        }

        $membership = $this->subsidiaries->findByHoldingAndBusiness($holdingId, $businessId);

        if (! $membership || $membership->status() !== SubsidiaryStatus::Active) {
            throw new InvalidArgumentException("Business [{$businessId}] is not an active member of Holding [{$holdingId}].");
        }
    }
}
