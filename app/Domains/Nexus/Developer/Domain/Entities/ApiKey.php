<?php

namespace App\Domains\Nexus\Developer\Domain\Entities;

use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;
use DateTimeImmutable;

/**
 * A revocable credential a Business issues to authenticate its own (or a
 * third-party integration's, acting on the Business's behalf) calls
 * against the Public REST API (Phase 9/M2) — the Business-scoped
 * counterpart to Core's AgentToken (the Agent-to-Agent MCP credential).
 * Deliberately a separate credential rather than reusing AgentToken: an
 * AgentToken authenticates *an Agent* to negotiate; an ApiKey authenticates
 * *software the Business owns* to read/manage its own account data —
 * different holder, different blast radius, so a leaked API key can be
 * revoked without touching the Agent's own negotiating credential.
 * Hashing follows the exact AgentToken::hash()/matches() pattern (SHA-256,
 * hash_equals, plaintext never persisted) — the one digital-signature
 * precedent in this codebase, reused rather than reinvented.
 */
final class ApiKey
{
    /**
     * @param list<ApiKeyScope> $scopes
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private readonly string $keyHash,
        private readonly string $keyPrefix,
        private readonly ?string $label,
        private readonly array $scopes,
        private ?DateTimeImmutable $lastUsedAt,
        private readonly ?DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $revokedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<ApiKeyScope> $scopes
     */
    public static function issue(
        int $businessId,
        string $keyHash,
        string $keyPrefix,
        ?string $label,
        array $scopes,
        ?DateTimeImmutable $expiresAt = null,
    ): self {
        return new self(
            id: null,
            businessId: $businessId,
            keyHash: $keyHash,
            keyPrefix: $keyPrefix,
            label: $label,
            scopes: $scopes,
            lastUsedAt: null,
            expiresAt: $expiresAt,
            revokedAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * Single source of truth for the hashing algorithm, shared by
     * IssueApiKeyAction (generation) and AuthenticateApiKeyAction
     * (verification) so they can never drift apart.
     */
    public static function hash(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    public function matches(string $plainKey): bool
    {
        return hash_equals($this->keyHash, self::hash($plainKey));
    }

    public function hasScope(ApiKeyScope $scope): bool
    {
        return in_array($scope, $this->scopes, true);
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

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function keyHash(): string
    {
        return $this->keyHash;
    }

    public function keyPrefix(): string
    {
        return $this->keyPrefix;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    /**
     * @return list<ApiKeyScope>
     */
    public function scopes(): array
    {
        return $this->scopes;
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
