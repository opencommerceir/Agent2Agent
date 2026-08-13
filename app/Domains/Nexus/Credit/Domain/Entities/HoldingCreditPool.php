<?php

namespace App\Domains\Nexus\Credit\Domain\Entities;

use App\Domains\Nexus\Credit\Domain\Exceptions\InsufficientCreditException;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Phase 7's "shared credit pool" — one balance per Holding (unique
 * holding_id), a sibling of CreditBalance rather than a nullable/polymorphic
 * column bolted onto it: CreditBalance has always been strictly 1:1 with a
 * Business, and widening that invariant to also mean "or a Holding" would
 * make every existing CreditBalance read ambiguous. Same credit()/debit()
 * shape, same InsufficientCreditException, deliberately duplicated rather
 * than shared because the two things being debited are genuinely different
 * aggregates. Framework-free (Domain Layer Rules).
 */
final class HoldingCreditPool
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $holdingId,
        private int $balance,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function open(int $holdingId, int $startingBalance = 0): self
    {
        return new self(
            id: null,
            holdingId: $holdingId,
            balance: $startingBalance,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function credit(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Credit amount must be positive, got [{$amount}].");
        }

        $this->balance += $amount;
    }

    public function debit(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Debit amount must be positive, got [{$amount}].");
        }

        if ($amount > $this->balance) {
            throw new InsufficientCreditException(
                "Holding [{$this->holdingId}] pool has [{$this->balance}] credits, needs [{$amount}]."
            );
        }

        $this->balance -= $amount;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function holdingId(): int
    {
        return $this->holdingId;
    }

    public function balance(): int
    {
        return $this->balance;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
