<?php

namespace App\Modules\Loyalty\Application\DTOs;

use App\Modules\Loyalty\Domain\Entities\Reward;

final class RewardData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $rewardType,
        public readonly int $pointsRequired,
        public readonly ?int $discountAmount,
        public readonly bool $isActive,
    ) {
    }

    public static function fromEntity(Reward $reward): self
    {
        return new self(
            id: $reward->id(),
            tenantId: $reward->tenantId(),
            name: $reward->name(),
            description: $reward->description(),
            rewardType: $reward->rewardType()->value,
            pointsRequired: $reward->pointsRequired()->value(),
            discountAmount: $reward->discountAmount(),
            isActive: $reward->isActive(),
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
            'name' => $this->name,
            'description' => $this->description,
            'rewardType' => $this->rewardType,
            'pointsRequired' => $this->pointsRequired,
            'discountAmount' => $this->discountAmount,
            'isActive' => $this->isActive,
        ];
    }
}
