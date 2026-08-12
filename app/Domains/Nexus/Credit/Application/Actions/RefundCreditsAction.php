<?php

namespace App\Domains\Nexus\Credit\Application\Actions;

use App\Domains\Nexus\Credit\Application\DTOs\CreditBalanceData;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;

/**
 * A thin, type-safe wrapper around GrantCreditsAction — exists so a caller
 * refunding credits (e.g. a rejected/cancelled negotiation that already
 * charged nexus.negotiation.propose) can never accidentally mistag the
 * ledger row as an AdminGrant or a Purchase.
 */
final class RefundCreditsAction
{
    public function __construct(
        private readonly GrantCreditsAction $grantCredits,
    ) {
    }

    public function execute(int $businessId, int $amount, string $reason, ?int $relatedId = null): CreditBalanceData
    {
        return $this->grantCredits->execute($businessId, $amount, CreditTransactionType::Refund, $reason, $relatedId);
    }
}
