<?php

namespace App\Domains\Nexus\Credit\Domain\Entities;

use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use DateTimeImmutable;

/**
 * One immutable ledger row for the pool — same "fact, not a workflow" shape
 * as CreditTransaction, kept as a sibling table rather than a nullable
 * holding_id column on nexus_credit_transactions for the same reason
 * HoldingCreditPool is a sibling of CreditBalance. `businessId` records
 * which member actually triggered the change (a contribution, or a CostGate
 * deduction while acting on the Holding's behalf) — nullable only in
 * principle, always populated by every Action that writes one today.
 */
final class HoldingCreditPoolTransaction
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $holdingId,
        private readonly ?int $businessId,
        private readonly CreditTransactionType $type,
        private readonly int $amount,
        private readonly string $reason,
        private readonly int $balanceAfter,
        private readonly ?int $relatedId,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        int $holdingId,
        ?int $businessId,
        CreditTransactionType $type,
        int $amount,
        string $reason,
        int $balanceAfter,
        ?int $relatedId = null,
    ): self {
        return new self(
            id: null,
            holdingId: $holdingId,
            businessId: $businessId,
            type: $type,
            amount: $amount,
            reason: $reason,
            balanceAfter: $balanceAfter,
            relatedId: $relatedId,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function holdingId(): int
    {
        return $this->holdingId;
    }

    public function businessId(): ?int
    {
        return $this->businessId;
    }

    public function type(): CreditTransactionType
    {
        return $this->type;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function balanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function relatedId(): ?int
    {
        return $this->relatedId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
