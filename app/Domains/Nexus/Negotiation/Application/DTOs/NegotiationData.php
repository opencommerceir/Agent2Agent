<?php

namespace App\Domains\Nexus\Negotiation\Application\DTOs;

use App\Domains\Nexus\Negotiation\Domain\Entities\Negotiation;

/**
 * Structured data transfer for Negotiation across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class NegotiationData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $initiatorBusinessId,
        public readonly int $counterpartyBusinessId,
        public readonly string $catalogItemType,
        public readonly int $catalogItemId,
        public readonly string $status,
        public readonly array $currentTerms,
        public readonly int $roundCount,
        public readonly int $maxRounds,
        public readonly ?string $rejectionReason,
    ) {
    }

    public static function fromEntity(Negotiation $negotiation): self
    {
        return new self(
            id: $negotiation->id(),
            initiatorBusinessId: $negotiation->initiatorBusinessId(),
            counterpartyBusinessId: $negotiation->counterpartyBusinessId(),
            catalogItemType: $negotiation->catalogItemType()->value,
            catalogItemId: $negotiation->catalogItemId(),
            status: $negotiation->status()->value,
            currentTerms: $negotiation->currentTerms()->toArray(),
            roundCount: $negotiation->roundCount(),
            maxRounds: $negotiation->maxRounds(),
            rejectionReason: $negotiation->rejectionReason(),
        );
    }

    /**
     * @return array{id: ?int, initiatorBusinessId: int, counterpartyBusinessId: int, catalogItemType: string, catalogItemId: int, status: string, currentTerms: array, roundCount: int, maxRounds: int, rejectionReason: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'initiatorBusinessId' => $this->initiatorBusinessId,
            'counterpartyBusinessId' => $this->counterpartyBusinessId,
            'catalogItemType' => $this->catalogItemType,
            'catalogItemId' => $this->catalogItemId,
            'status' => $this->status,
            'currentTerms' => $this->currentTerms,
            'roundCount' => $this->roundCount,
            'maxRounds' => $this->maxRounds,
            'rejectionReason' => $this->rejectionReason,
        ];
    }
}
