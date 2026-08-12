<?php

namespace App\Domains\Nexus\Contract\Domain\Entities;

use App\Domains\Nexus\Contract\Domain\Exceptions\InvalidDisputeCaseStateException;
use App\Domains\Nexus\Contract\Domain\ValueObjects\DisputeCaseStatus;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The real evidence/mediation/arbitration workflow layered on top of a
 * Disputed Escrow — DisputeEscrowAction itself stays the simple "either
 * party can flag it" entry point; this is what happens next. One
 * DisputeCase per disputed Escrow, opened automatically
 * (OpenDisputeCaseOnEscrowDisputedListener, on EscrowWasDisputed).
 *
 * `businessAId`/`businessBId` are denormalized straight from Escrow (not
 * looked up at authorization time) so SubmitDisputeEvidenceAction can
 * check party membership entirely on its own — the same
 * self-contained-authorization convention Negotiation/Escrow already
 * follow. `evidence` is a plain array of {businessId, note, submittedAt}
 * text notes — no file/attachment infra exists in this codebase to back
 * anything richer, an honest limitation, not an oversight (mirrors
 * Escrow's own "state-tracking layer, not real custody" docblock).
 *
 * State machine mirrors the codebase-wide ALLOWED_TRANSITIONS +
 * transitionTo() guard shape. `Resolved` is terminal; `resolution`
 * ('refund_buyer'|'release_seller') is set exactly once, by
 * ArbitrateDisputeAction, alongside the matching Escrow transition
 * (disputed -> Refunded/Released, both now legal per Escrow's own
 * ALLOWED_TRANSITIONS after Phase 6/M3).
 */
final class DisputeCase
{
    /**
     * @var array<string, list<DisputeCaseStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'open' => [DisputeCaseStatus::Mediation, DisputeCaseStatus::Resolved],
        'mediation' => [DisputeCaseStatus::Resolved],
        'resolved' => [],
    ];

    /**
     * @param  list<array{businessId: int, note: string, submittedAt: string}>  $evidence
     */
    private function __construct(
        private readonly ?int $id,
        private readonly int $escrowId,
        private readonly int $negotiationId,
        private readonly int $businessAId,
        private readonly int $businessBId,
        private readonly int $openedByBusinessId,
        private readonly ?string $reason,
        private array $evidence,
        private DisputeCaseStatus $status,
        private ?string $resolution,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $resolvedAt,
    ) {
    }

    public static function open(
        int $escrowId,
        int $negotiationId,
        int $businessAId,
        int $businessBId,
        int $openedByBusinessId,
        ?string $reason,
    ): self {
        return new self(
            id: null,
            escrowId: $escrowId,
            negotiationId: $negotiationId,
            businessAId: $businessAId,
            businessBId: $businessBId,
            openedByBusinessId: $openedByBusinessId,
            reason: $reason,
            evidence: [],
            status: DisputeCaseStatus::Open,
            resolution: null,
            createdAt: new DateTimeImmutable(),
            resolvedAt: null,
        );
    }

    /**
     * @param  list<array{businessId: int, note: string, submittedAt: string}>  $evidence
     */
    public static function reconstruct(
        int $id,
        int $escrowId,
        int $negotiationId,
        int $businessAId,
        int $businessBId,
        int $openedByBusinessId,
        ?string $reason,
        array $evidence,
        DisputeCaseStatus $status,
        ?string $resolution,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $resolvedAt,
    ): self {
        return new self(
            id: $id,
            escrowId: $escrowId,
            negotiationId: $negotiationId,
            businessAId: $businessAId,
            businessBId: $businessBId,
            openedByBusinessId: $openedByBusinessId,
            reason: $reason,
            evidence: $evidence,
            status: $status,
            resolution: $resolution,
            createdAt: $createdAt,
            resolvedAt: $resolvedAt,
        );
    }

    public function addEvidence(int $businessId, string $note): void
    {
        if (! $this->isParty($businessId)) {
            throw new InvalidArgumentException("Business [{$businessId}] is not a party to this DisputeCase.");
        }

        $this->evidence[] = [
            'businessId' => $businessId,
            'note' => $note,
            'submittedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    public function moveToMediation(): void
    {
        $this->transitionTo(DisputeCaseStatus::Mediation);
    }

    public function resolve(string $resolution): void
    {
        if (! in_array($resolution, ['refund_buyer', 'release_seller'], true)) {
            throw new InvalidArgumentException("Unknown dispute resolution [{$resolution}].");
        }

        $this->transitionTo(DisputeCaseStatus::Resolved);
        $this->resolution = $resolution;
        $this->resolvedAt = new DateTimeImmutable();
    }

    public function isParty(int $businessId): bool
    {
        return $businessId === $this->businessAId || $businessId === $this->businessBId;
    }

    private function transitionTo(DisputeCaseStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidDisputeCaseStateException(
                "DisputeCase cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function escrowId(): int
    {
        return $this->escrowId;
    }

    public function negotiationId(): int
    {
        return $this->negotiationId;
    }

    public function businessAId(): int
    {
        return $this->businessAId;
    }

    public function businessBId(): int
    {
        return $this->businessBId;
    }

    public function openedByBusinessId(): int
    {
        return $this->openedByBusinessId;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * @return list<array{businessId: int, note: string, submittedAt: string}>
     */
    public function evidence(): array
    {
        return $this->evidence;
    }

    public function status(): DisputeCaseStatus
    {
        return $this->status;
    }

    public function resolution(): ?string
    {
        return $this->resolution;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function resolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
