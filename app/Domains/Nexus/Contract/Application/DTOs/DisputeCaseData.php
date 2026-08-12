<?php

namespace App\Domains\Nexus\Contract\Application\DTOs;

use App\Domains\Nexus\Contract\Domain\Entities\DisputeCase;

/**
 * Structured data transfer for DisputeCase across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class DisputeCaseData
{
    public function __construct(
        public readonly int $id,
        public readonly int $escrowId,
        public readonly int $negotiationId,
        public readonly int $openedByBusinessId,
        public readonly ?string $reason,
        public readonly array $evidence,
        public readonly string $status,
        public readonly ?string $resolution,
        public readonly string $createdAt,
        public readonly ?string $resolvedAt,
    ) {
    }

    public static function fromEntity(DisputeCase $disputeCase): self
    {
        return new self(
            id: $disputeCase->id(),
            escrowId: $disputeCase->escrowId(),
            negotiationId: $disputeCase->negotiationId(),
            openedByBusinessId: $disputeCase->openedByBusinessId(),
            reason: $disputeCase->reason(),
            evidence: $disputeCase->evidence(),
            status: $disputeCase->status()->value,
            resolution: $disputeCase->resolution(),
            createdAt: $disputeCase->createdAt()->format(DATE_ATOM),
            resolvedAt: $disputeCase->resolvedAt()?->format(DATE_ATOM),
        );
    }

    /**
     * @return array{id: int, escrowId: int, negotiationId: int, openedByBusinessId: int, reason: ?string, evidence: array, status: string, resolution: ?string, createdAt: string, resolvedAt: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'escrowId' => $this->escrowId,
            'negotiationId' => $this->negotiationId,
            'openedByBusinessId' => $this->openedByBusinessId,
            'reason' => $this->reason,
            'evidence' => $this->evidence,
            'status' => $this->status,
            'resolution' => $this->resolution,
            'createdAt' => $this->createdAt,
            'resolvedAt' => $this->resolvedAt,
        ];
    }
}
