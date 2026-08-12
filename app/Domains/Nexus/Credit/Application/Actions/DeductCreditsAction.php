<?php

namespace App\Domains\Nexus\Credit\Application\Actions;

use App\Domains\Nexus\Credit\Application\DTOs\CreditBalanceData;
use App\Domains\Nexus\Credit\Domain\Entities\CreditTransaction;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditTransactionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use InvalidArgumentException;

/**
 * The single write path that ever decreases a balance. Bubbles
 * CreditBalance::debit()'s own InsufficientCreditException unchanged —
 * that's what SpendCreditsForActionAction (Phase 3/M2, the roadmap's
 * "CostGate") catches to reject a gated MCP capability with a clean 409.
 */
final class DeductCreditsAction
{
    public function __construct(
        private readonly CreditBalanceRepositoryInterface $balances,
        private readonly CreditTransactionRepositoryInterface $transactions,
    ) {
    }

    public function execute(int $businessId, int $amount, string $reason, ?int $relatedId = null): CreditBalanceData
    {
        $balance = $this->balances->findByBusinessId($businessId);

        if (! $balance) {
            throw new InvalidArgumentException("Business [{$businessId}] has no credit balance yet.");
        }

        $balance->debit($amount);
        $balance = $this->balances->save($balance);

        $this->transactions->save(CreditTransaction::record(
            businessId: $businessId,
            type: CreditTransactionType::Deduction,
            amount: $amount,
            reason: $reason,
            balanceAfter: $balance->balance(),
            relatedId: $relatedId,
        ));

        return CreditBalanceData::fromEntity($balance);
    }
}
