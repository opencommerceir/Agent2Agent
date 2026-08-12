<?php

namespace App\Domains\Nexus\Negotiation\Application\DTOs;

use App\Domains\Nexus\Negotiation\Domain\Entities\NegotiationMessage;

/**
 * Structured data transfer for NegotiationMessage across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class NegotiationMessageData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $negotiationId,
        public readonly int $senderBusinessId,
        public readonly string $type,
        public readonly array $terms,
        public readonly ?array $reasoning,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(NegotiationMessage $message): self
    {
        return new self(
            id: $message->id(),
            negotiationId: $message->negotiationId(),
            senderBusinessId: $message->senderBusinessId(),
            type: $message->type()->value,
            terms: $message->terms()->toArray(),
            reasoning: $message->reasoning(),
            createdAt: $message->createdAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array{id: ?int, negotiationId: int, senderBusinessId: int, type: string, terms: array, reasoning: ?array, createdAt: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'negotiationId' => $this->negotiationId,
            'senderBusinessId' => $this->senderBusinessId,
            'type' => $this->type,
            'terms' => $this->terms,
            'reasoning' => $this->reasoning,
            'createdAt' => $this->createdAt,
        ];
    }
}
