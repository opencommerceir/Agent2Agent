<?php

namespace App\Domains\Nexus\Business\Domain\Entities;

use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionTrigger;
use DateTimeImmutable;

/**
 * One immutable ledger row per suspend/reactivate — a fact, not a
 * workflow with states, the exact same shape CreditTransaction/
 * LLMUsageLog already establish for this codebase's "no generic AuditLog,
 * a domain-specific immutable ledger instead" convention. Covers BOTH a
 * manual admin decision and an automatic Phase 6/M4 fraud-rule suspension
 * in one table (`triggeredBy` distinguishes them) rather than a second,
 * near-identical ledger for the automatic case.
 */
final class SuspensionRecord
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private readonly SuspensionAction $action,
        private readonly string $reason,
        private readonly SuspensionTrigger $triggeredBy,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        int $businessId,
        SuspensionAction $action,
        string $reason,
        SuspensionTrigger $triggeredBy,
    ): self {
        return new self(
            id: null,
            businessId: $businessId,
            action: $action,
            reason: $reason,
            triggeredBy: $triggeredBy,
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

    public function action(): SuspensionAction
    {
        return $this->action;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function triggeredBy(): SuspensionTrigger
    {
        return $this->triggeredBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
