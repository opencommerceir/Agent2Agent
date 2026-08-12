<?php

namespace App\Domains\Nexus\Credit\Domain\Entities;

use App\Domains\Nexus\Credit\Domain\Exceptions\InsufficientCreditException;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A Business's spendable credit balance — one row per Business (unique
 * business_id, same 1:1-per-business shape as Agent). Framework-free
 * (Domain Layer Rules). Denominated in plain credit units, not Money —
 * "1,000 credits" (docs/claude/monetization.md), not a currency amount;
 * the real-money side only exists at purchase time (Credit's own Money VO,
 * Phase 3/M3).
 */
final class CreditBalance
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private int $balance,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function open(int $businessId, int $startingBalance = 0): self
    {
        return new self(
            id: null,
            businessId: $businessId,
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
                "Business [{$this->businessId}] has [{$this->balance}] credits, needs [{$amount}]."
            );
        }

        $this->balance -= $amount;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
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
