<?php

namespace App\Domains\Nexus\Credit\Application\DTOs;

use App\Domains\Nexus\Credit\Domain\Entities\HoldingCreditPool;

final class HoldingCreditPoolData
{
    public function __construct(
        public readonly int $holdingId,
        public readonly int $balance,
    ) {
    }

    public static function fromEntity(HoldingCreditPool $pool): self
    {
        return new self(
            holdingId: $pool->holdingId(),
            balance: $pool->balance(),
        );
    }

    public function toArray(): array
    {
        return [
            'holdingId' => $this->holdingId,
            'balance' => $this->balance,
        ];
    }
}
