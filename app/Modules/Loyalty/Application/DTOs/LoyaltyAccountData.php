<?php

namespace App\Modules\Loyalty\Application\DTOs;

use App\Modules\Loyalty\Domain\Entities\LoyaltyAccount;

/**
 * Structured data transfer for LoyaltyAccount across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class LoyaltyAccountData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $customerId,
        public readonly int $totalPointsEarned,
        public readonly int $totalPointsRedeemed,
        public readonly int $currentBalance,
    ) {
    }

    public static function fromEntity(LoyaltyAccount $account): self
    {
        return new self(
            id: $account->id(),
            tenantId: $account->tenantId(),
            customerId: $account->customerId(),
            totalPointsEarned: $account->totalPointsEarned()->value(),
            totalPointsRedeemed: $account->totalPointsRedeemed()->value(),
            currentBalance: $account->currentBalance()->value(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'customerId' => $this->customerId,
            'totalPointsEarned' => $this->totalPointsEarned,
            'totalPointsRedeemed' => $this->totalPointsRedeemed,
            'currentBalance' => $this->currentBalance,
        ];
    }
}
