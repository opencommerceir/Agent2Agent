<?php

namespace App\Domains\Nexus\Credit\Domain\Entities;

use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use DateTimeImmutable;

/**
 * One immutable ledger row — a fact ("this business's balance changed by
 * this much, for this reason"), not a workflow with states (unlike
 * Negotiation/PaymentSession's ALLOWED_TRANSITIONS shape). This ledger
 * doubles as the audit trail CLAUDE.md requires for credit-gated Agent
 * actions ("لاگ دقیق تمام تراکنش‌ها", docs/nexus-roadmap.md Phase 3) — no
 * separate generic AuditLog exists anywhere in the codebase, and adding
 * one would be scope creep the roadmap doesn't ask for.
 */
final class CreditTransaction
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private readonly CreditTransactionType $type,
        private readonly int $amount,
        private readonly string $reason,
        private readonly int $balanceAfter,
        private readonly ?int $relatedId,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        int $businessId,
        CreditTransactionType $type,
        int $amount,
        string $reason,
        int $balanceAfter,
        ?int $relatedId = null,
    ): self {
        return new self(
            id: null,
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

    public function businessId(): int
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
