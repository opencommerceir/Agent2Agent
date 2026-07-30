<?php

namespace App\Core\Domain\Entities;

use DateTimeImmutable;

/**
 * A revocable credential issued to an Agent. Deliberately separate from the
 * Agent entity itself: an Agent can hold many tokens over its lifetime, and
 * rotating/revoking a token must never require touching Agent identity data.
 *
 * The raw token value is never stored anywhere — only its SHA-256 hash.
 * hash()/matches() use plain PHP primitives (hash(), hash_equals()) so this
 * entity stays framework-free, per the Domain Layer Rules.
 */
final class AgentToken
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $agentId,
        private readonly string $tokenHash,
        private ?string $label,
        private ?DateTimeImmutable $lastUsedAt,
        private readonly ?DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $revokedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function issue(
        int $agentId,
        string $tokenHash,
        ?string $label = null,
        ?DateTimeImmutable $expiresAt = null,
    ): self {
        return new self(
            id: null,
            agentId: $agentId,
            tokenHash: $tokenHash,
            label: $label,
            lastUsedAt: null,
            expiresAt: $expiresAt,
            revokedAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * Single source of truth for the hashing algorithm, shared by token
     * generation (GenerateAgentTokenAction) and verification
     * (AuthenticateAgentAction) so they can never drift apart.
     */
    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function matches(string $plainToken): bool
    {
        return hash_equals($this->tokenHash, self::hash($plainToken));
    }

    public function markUsed(): void
    {
        $this->lastUsedAt = new DateTimeImmutable();
    }

    public function revoke(): void
    {
        $this->revokedAt = new DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function agentId(): int
    {
        return $this->agentId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
