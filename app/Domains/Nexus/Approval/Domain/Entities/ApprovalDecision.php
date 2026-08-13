<?php

namespace App\Domains\Nexus\Approval\Domain\Entities;

use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalDecisionOutcome;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use DateTimeImmutable;

/**
 * One immutable ledger row per level decision — a fact ("this owner
 * decided this at this level"), same shape as CreditTransaction/
 * LLMUsageLog, not a state machine of its own.
 */
final class ApprovalDecision
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $approvalRequestId,
        private readonly int $levelIndex,
        private readonly TeamMemberRole $roleRequired,
        private readonly int $decidedByOwnerId,
        private readonly ApprovalDecisionOutcome $decision,
        private readonly DateTimeImmutable $decidedAt,
    ) {
    }

    public static function record(
        int $approvalRequestId,
        int $levelIndex,
        TeamMemberRole $roleRequired,
        int $decidedByOwnerId,
        ApprovalDecisionOutcome $decision,
    ): self {
        return new self(
            id: null,
            approvalRequestId: $approvalRequestId,
            levelIndex: $levelIndex,
            roleRequired: $roleRequired,
            decidedByOwnerId: $decidedByOwnerId,
            decision: $decision,
            decidedAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function approvalRequestId(): int
    {
        return $this->approvalRequestId;
    }

    public function levelIndex(): int
    {
        return $this->levelIndex;
    }

    public function roleRequired(): TeamMemberRole
    {
        return $this->roleRequired;
    }

    public function decidedByOwnerId(): int
    {
        return $this->decidedByOwnerId;
    }

    public function decision(): ApprovalDecisionOutcome
    {
        return $this->decision;
    }

    public function decidedAt(): DateTimeImmutable
    {
        return $this->decidedAt;
    }
}
