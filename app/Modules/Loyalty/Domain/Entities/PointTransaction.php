<?php

namespace App\Modules\Loyalty\Domain\Entities;

use App\Modules\Loyalty\Domain\Exceptions\InvalidPointsException;
use App\Modules\Loyalty\Domain\ValueObjects\TransactionType;
use DateTimeImmutable;

/**
 * One immutable ledger row: a fact about a LoyaltyAccount's balance
 * changing, never edited after creation (same shape Commerce's
 * OrderItem/Workflows' WorkflowLog already establish for a
 * write-once-read-many child record — HANDOFF gotcha #10).
 *
 * `points` is a plain signed int, deliberately not the Points VO — Points
 * only ever represents a non-negative *amount*; this column is a signed
 * *delta* (rule §d schema: "integer, مثبت یا منفی"). record() enforces
 * the sign-by-type invariant so a caller can never accidentally persist
 * a `redeem` entry with a positive delta or vice versa: `earn`/`bonus`
 * must be > 0, `redeem`/`expire` must be < 0, `adjust` may be either but
 * never exactly 0 (a zero-delta ledger entry records nothing and would
 * only be a bug upstream).
 */
final class PointTransaction
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $loyaltyAccountId,
        private readonly int $points,
        private readonly TransactionType $transactionType,
        private readonly ?string $description,
        private readonly ?int $referenceId,
        private readonly ?DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        int $tenantId,
        int $loyaltyAccountId,
        int $points,
        TransactionType $transactionType,
        ?string $description = null,
        ?int $referenceId = null,
        ?DateTimeImmutable $expiresAt = null,
    ): self {
        if ($points === 0) {
            throw new InvalidPointsException('A PointTransaction cannot record a zero-point delta.');
        }

        if (in_array($transactionType, [TransactionType::Earn, TransactionType::Bonus], true) && $points < 0) {
            throw new InvalidPointsException("A [{$transactionType->value}] transaction must record a positive point delta.");
        }

        if (in_array($transactionType, [TransactionType::Redeem, TransactionType::Expire], true) && $points > 0) {
            throw new InvalidPointsException("A [{$transactionType->value}] transaction must record a negative point delta.");
        }

        return new self(
            id: null,
            tenantId: $tenantId,
            loyaltyAccountId: $loyaltyAccountId,
            points: $points,
            transactionType: $transactionType,
            description: $description,
            referenceId: $referenceId,
            expiresAt: $expiresAt,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function loyaltyAccountId(): int
    {
        return $this->loyaltyAccountId;
    }

    public function points(): int
    {
        return $this->points;
    }

    public function transactionType(): TransactionType
    {
        return $this->transactionType;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function referenceId(): ?int
    {
        return $this->referenceId;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
