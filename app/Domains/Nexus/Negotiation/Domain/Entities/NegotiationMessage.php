<?php

namespace App\Domains\Nexus\Negotiation\Domain\Entities;

use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationMessageType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use DateTimeImmutable;

/**
 * One turn in a Negotiation's history — a proposal, counter, accept, or
 * reject, with the terms on the table at that moment. `reasoning` is
 * populated by M5's NegotiationReasoningService; nullable so this entity
 * (and M3's Actions) work standalone before M5 exists.
 */
final class NegotiationMessage
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $negotiationId,
        private readonly int $senderBusinessId,
        private readonly NegotiationMessageType $type,
        private readonly NegotiationTerms $terms,
        private readonly ?array $reasoning,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        int $negotiationId,
        int $senderBusinessId,
        NegotiationMessageType $type,
        NegotiationTerms $terms,
        ?array $reasoning = null,
    ): self {
        return new self(
            id: null,
            negotiationId: $negotiationId,
            senderBusinessId: $senderBusinessId,
            type: $type,
            terms: $terms,
            reasoning: $reasoning,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function negotiationId(): int
    {
        return $this->negotiationId;
    }

    public function senderBusinessId(): int
    {
        return $this->senderBusinessId;
    }

    public function type(): NegotiationMessageType
    {
        return $this->type;
    }

    public function terms(): NegotiationTerms
    {
        return $this->terms;
    }

    public function reasoning(): ?array
    {
        return $this->reasoning;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
