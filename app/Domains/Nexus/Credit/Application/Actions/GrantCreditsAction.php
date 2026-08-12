<?php

namespace App\Domains\Nexus\Credit\Application\Actions;

use App\Domains\Nexus\Credit\Application\DTOs\CreditBalanceData;
use App\Domains\Nexus\Credit\Domain\Entities\CreditBalance;
use App\Domains\Nexus\Credit\Domain\Entities\CreditTransaction;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditTransactionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;

/**
 * The single write path that ever increases a balance — starting-balance
 * provisioning (GrantStartingCreditsOnBusinessVerifiedListener), purchase
 * fulfillment (Phase 3/M3's ConfirmCreditPurchaseAction), and admin manual
 * grants all funnel through here so every increase is ledgered the same
 * way. Opens a CreditBalance row on first use rather than requiring a
 * separate "open account" step — mirrors how Agent/Product don't need a
 * pre-creation step either.
 */
final class GrantCreditsAction
{
    public function __construct(
        private readonly CreditBalanceRepositoryInterface $balances,
        private readonly CreditTransactionRepositoryInterface $transactions,
    ) {
    }

    public function execute(
        int $businessId,
        int $amount,
        CreditTransactionType $type,
        string $reason,
        ?int $relatedId = null,
    ): CreditBalanceData {
        $balance = $this->balances->findByBusinessId($businessId) ?? CreditBalance::open($businessId);
        $balance->credit($amount);
        $balance = $this->balances->save($balance);

        $this->transactions->save(CreditTransaction::record(
            businessId: $businessId,
            type: $type,
            amount: $amount,
            reason: $reason,
            balanceAfter: $balance->balance(),
            relatedId: $relatedId,
        ));

        return CreditBalanceData::fromEntity($balance);
    }
}
