<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\Entities\AgentToken;

/**
 * Structured data transfer for AgentToken across layers.
 *
 * `plainToken` is only ever populated once, by GenerateAgentTokenAction,
 * at the exact moment a token is issued — it is never reconstructible
 * afterwards because only the hash is persisted. Every other place that
 * builds this DTO (e.g. listing a agent's tokens) must leave it null.
 */
final class AgentTokenData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $agentId,
        public readonly ?string $label,
        public readonly ?string $plainToken,
        public readonly ?string $expiresAt,
    ) {
    }

    public static function issued(AgentToken $token, string $plainToken): self
    {
        return new self(
            id: $token->id(),
            agentId: $token->agentId(),
            label: $token->label(),
            plainToken: $plainToken,
            expiresAt: $token->expiresAt()?->format(DATE_ATOM),
        );
    }

    public static function fromEntity(AgentToken $token): self
    {
        return new self(
            id: $token->id(),
            agentId: $token->agentId(),
            label: $token->label(),
            plainToken: null,
            expiresAt: $token->expiresAt()?->format(DATE_ATOM),
        );
    }

    /**
     * @return array{id: ?int, agentId: int, label: ?string, plainToken: ?string, expiresAt: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'agentId' => $this->agentId,
            'label' => $this->label,
            'plainToken' => $this->plainToken,
            'expiresAt' => $this->expiresAt,
        ];
    }
}
