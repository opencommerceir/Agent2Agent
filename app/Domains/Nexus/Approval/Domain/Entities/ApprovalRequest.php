<?php

namespace App\Domains\Nexus\Approval\Domain\Entities;

use App\Domains\Nexus\Approval\Domain\Exceptions\InvalidApprovalRequestStateException;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalLevel;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalRequestStatus;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use DateTimeImmutable;

/**
 * One open request per Negotiation (unique negotiationId) — opened by
 * OpenApprovalRequestForDealAction the moment AcceptDealAction's own
 * authority_limits gate trips, snapshotting `requiredLevels` from the
 * Business's ApprovalPolicy at that exact moment ("compute once, apply
 * durably later," the same precedent Escrow's platformFeePercent snapshot
 * already established — a policy edited mid-chain never reshapes a request
 * already in flight). Framework-free (Domain Layer Rules).
 */
final class ApprovalRequest
{
    /**
     * @param  list<ApprovalLevel>  $requiredLevels
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $negotiationId,
        private readonly int $businessId,
        private readonly array $requiredLevels,
        private int $currentLevelIndex,
        private ApprovalRequestStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param  list<ApprovalLevel>  $requiredLevels
     */
    public static function open(int $negotiationId, int $businessId, array $requiredLevels): self
    {
        return new self(
            id: null,
            negotiationId: $negotiationId,
            businessId: $businessId,
            requiredLevels: $requiredLevels,
            currentLevelIndex: 0,
            status: ApprovalRequestStatus::Pending,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * Advances past the current level — marks the whole chain Completed
     * once the last level has approved.
     */
    public function approveCurrentLevel(): void
    {
        $this->assertPending();

        $this->currentLevelIndex++;

        if ($this->currentLevelIndex >= count($this->requiredLevels)) {
            $this->status = ApprovalRequestStatus::Completed;
        }
    }

    public function reject(): void
    {
        $this->assertPending();

        $this->status = ApprovalRequestStatus::Rejected;
    }

    private function assertPending(): void
    {
        if ($this->status !== ApprovalRequestStatus::Pending) {
            throw new InvalidApprovalRequestStateException(
                "ApprovalRequest [{$this->id}] is [{$this->status->value}], not pending."
            );
        }
    }

    public function currentRequiredRole(): TeamMemberRole
    {
        return $this->requiredLevels[$this->currentLevelIndex]->role;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function negotiationId(): int
    {
        return $this->negotiationId;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    /**
     * @return list<ApprovalLevel>
     */
    public function requiredLevels(): array
    {
        return $this->requiredLevels;
    }

    public function currentLevelIndex(): int
    {
        return $this->currentLevelIndex;
    }

    public function status(): ApprovalRequestStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
