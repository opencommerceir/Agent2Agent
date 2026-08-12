<?php

namespace App\Domains\Nexus\Negotiation\Domain\Entities;

use App\Domains\Nexus\Negotiation\Domain\Exceptions\InvalidNegotiationStateException;
use App\Domains\Nexus\Negotiation\Domain\Exceptions\NegotiationRoundLimitExceededException;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationStatus;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use DateTimeImmutable;

/**
 * This codebase's first genuinely cross-tenant aggregate — a deal between
 * two different Businesses' Agents, so both sides are first-class fields
 * (not "the tenant" singular the way every other entity in the app is).
 * Framework-free (Domain Layer Rules).
 *
 * State machine mirrors Commerce's own Subscription entity exactly: an
 * explicit ALLOWED_TRANSITIONS map + one guarded transitionTo() every
 * public transition method funnels through. `Expired` is modeled (the
 * roadmap's own `negotiation.timeout_seconds` config exists from Phase 0)
 * but, like Subscription's own `Expired` case, no method transitions into
 * it this stage — a documented gap, not an oversight; nothing in this
 * phase's request needs a scheduled expiry job.
 *
 * `accept()`/`reject()` are reachable from Proposed, Countered, AND
 * PendingApproval alike — the same "one guarded method, several legal
 * source states" shape Subscription::cancelImmediately() already uses.
 * Whether an accept() requires human approval first is a decision the
 * Application layer makes (AcceptDealAction, checking the accepting
 * Agent's own authority_limits) via requestApproval() instead of calling
 * accept() directly — the entity itself has no opinion on authority
 * limits, that concept belongs to the Agent domain, not here.
 */
final class Negotiation
{
    /**
     * @var array<string, list<NegotiationStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'proposed' => [NegotiationStatus::Countered, NegotiationStatus::Accepted, NegotiationStatus::PendingApproval, NegotiationStatus::Rejected, NegotiationStatus::Expired],
        'countered' => [NegotiationStatus::Countered, NegotiationStatus::Accepted, NegotiationStatus::PendingApproval, NegotiationStatus::Rejected, NegotiationStatus::Expired],
        'pending_approval' => [NegotiationStatus::Accepted, NegotiationStatus::Rejected],
        'accepted' => [],
        'rejected' => [],
        'expired' => [],
    ];

    public function __construct(
        private readonly ?int $id,
        private readonly int $initiatorBusinessId,
        private readonly int $initiatorTenantId,
        private readonly int $counterpartyBusinessId,
        private readonly int $counterpartyTenantId,
        private readonly CatalogItemType $catalogItemType,
        private readonly int $catalogItemId,
        private NegotiationStatus $status,
        private NegotiationTerms $currentTerms,
        private int $roundCount,
        private readonly int $maxRounds,
        private ?string $rejectionReason,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function propose(
        int $initiatorBusinessId,
        int $initiatorTenantId,
        int $counterpartyBusinessId,
        int $counterpartyTenantId,
        CatalogItemType $catalogItemType,
        int $catalogItemId,
        NegotiationTerms $terms,
        int $maxRounds,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: null,
            initiatorBusinessId: $initiatorBusinessId,
            initiatorTenantId: $initiatorTenantId,
            counterpartyBusinessId: $counterpartyBusinessId,
            counterpartyTenantId: $counterpartyTenantId,
            catalogItemType: $catalogItemType,
            catalogItemId: $catalogItemId,
            status: NegotiationStatus::Proposed,
            currentTerms: $terms,
            roundCount: 1,
            maxRounds: $maxRounds,
            rejectionReason: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function counter(NegotiationTerms $terms): void
    {
        if ($this->roundCount >= $this->maxRounds) {
            throw new NegotiationRoundLimitExceededException(
                "Negotiation [{$this->id}] has reached its round limit of [{$this->maxRounds}]."
            );
        }

        $this->transitionTo(NegotiationStatus::Countered);
        $this->currentTerms = $terms;
        $this->roundCount++;
    }

    public function requestApproval(): void
    {
        $this->transitionTo(NegotiationStatus::PendingApproval);
    }

    public function accept(): void
    {
        $this->transitionTo(NegotiationStatus::Accepted);
    }

    public function reject(?string $reason = null): void
    {
        $this->transitionTo(NegotiationStatus::Rejected);
        $this->rejectionReason = $reason;
    }

    public function isParty(int $businessId): bool
    {
        return $businessId === $this->initiatorBusinessId || $businessId === $this->counterpartyBusinessId;
    }

    public function otherParty(int $businessId): int
    {
        return $businessId === $this->initiatorBusinessId ? $this->counterpartyBusinessId : $this->initiatorBusinessId;
    }

    private function transitionTo(NegotiationStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidNegotiationStateException(
                "Negotiation cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function initiatorBusinessId(): int
    {
        return $this->initiatorBusinessId;
    }

    public function initiatorTenantId(): int
    {
        return $this->initiatorTenantId;
    }

    public function counterpartyBusinessId(): int
    {
        return $this->counterpartyBusinessId;
    }

    public function counterpartyTenantId(): int
    {
        return $this->counterpartyTenantId;
    }

    public function catalogItemType(): CatalogItemType
    {
        return $this->catalogItemType;
    }

    public function catalogItemId(): int
    {
        return $this->catalogItemId;
    }

    public function status(): NegotiationStatus
    {
        return $this->status;
    }

    public function currentTerms(): NegotiationTerms
    {
        return $this->currentTerms;
    }

    public function roundCount(): int
    {
        return $this->roundCount;
    }

    public function maxRounds(): int
    {
        return $this->maxRounds;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
