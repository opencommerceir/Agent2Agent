<?php

namespace App\Modules\Loyalty\Application\DTOs;

use App\Modules\Loyalty\Domain\Entities\Redemption;

final class RedemptionData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $loyaltyAccountId,
        public readonly int $rewardId,
        public readonly int $pointsUsed,
        public readonly string $status,
    ) {
    }

    public static function fromEntity(Redemption $redemption): self
    {
        return new self(
            id: $redemption->id(),
            tenantId: $redemption->tenantId(),
            loyaltyAccountId: $redemption->loyaltyAccountId(),
            rewardId: $redemption->rewardId(),
            pointsUsed: $redemption->pointsUsed()->value(),
            status: $redemption->status(),
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
            'loyaltyAccountId' => $this->loyaltyAccountId,
            'rewardId' => $this->rewardId,
            'pointsUsed' => $this->pointsUsed,
            'status' => $this->status,
        ];
    }
}
