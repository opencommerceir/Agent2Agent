<?php

namespace App\Domains\Nexus\Credit\Application\DTOs;

use App\Domains\Nexus\Credit\Domain\Entities\CreditBalance;

/**
 * Structured data transfer for a Credit balance across layers. Represents
 * data only — no business logic (DTO Conventions).
 */
final class CreditBalanceData
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $balance,
    ) {
    }

    public static function fromEntity(CreditBalance $balance): self
    {
        return new self(
            businessId: $balance->businessId(),
            balance: $balance->balance(),
        );
    }

    /**
     * @return array{businessId: int, balance: int}
     */
    public function toArray(): array
    {
        return [
            'businessId' => $this->businessId,
            'balance' => $this->balance,
        ];
    }
}
