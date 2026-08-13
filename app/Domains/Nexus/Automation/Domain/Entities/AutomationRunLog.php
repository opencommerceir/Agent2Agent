<?php

namespace App\Domains\Nexus\Automation\Domain\Entities;

use App\Domains\Nexus\Automation\Domain\ValueObjects\AutomationRunOutcome;
use DateTimeImmutable;

/**
 * An immutable ledger row for one real AutomationRule trigger attempt —
 * same "no updated_at, static factory" shape CreditTransaction/LLMUsageLog/
 * SuspensionRecord already establish (Phase 3/M1, Phase 4/M3, Phase 6/M4).
 * Only Triggered/Failed outcomes exist here (never "skipped, not due yet")
 * — see AutomationRule's own docblock and the migration's comment for why.
 * Framework-free (Domain Layer Rules).
 */
final class AutomationRunLog
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $automationRuleId,
        private readonly int $businessId,
        private readonly AutomationRunOutcome $outcome,
        private readonly string $detail,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(int $automationRuleId, int $businessId, AutomationRunOutcome $outcome, string $detail): self
    {
        return new self(
            id: null,
            automationRuleId: $automationRuleId,
            businessId: $businessId,
            outcome: $outcome,
            detail: $detail,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function automationRuleId(): int
    {
        return $this->automationRuleId;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function outcome(): AutomationRunOutcome
    {
        return $this->outcome;
    }

    public function detail(): string
    {
        return $this->detail;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
