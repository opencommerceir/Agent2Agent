<?php

namespace App\Domains\Nexus\Developer\Application\DTOs;

use App\Domains\Nexus\Developer\Domain\Entities\ApiKey;

/**
 * Structured data transfer for an API key across layers. Deliberately
 * never carries keyHash (or the plaintext key, which only
 * IssueApiKeyAction ever sees, once) — represents data only, no business
 * logic (DTO Conventions).
 */
final class ApiKeyData
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $keyPrefix,
        public readonly ?string $label,
        public readonly array $scopes,
        public readonly ?string $lastUsedAt,
        public readonly ?string $expiresAt,
        public readonly bool $isRevoked,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(ApiKey $apiKey): self
    {
        return new self(
            id: $apiKey->id(),
            keyPrefix: $apiKey->keyPrefix(),
            label: $apiKey->label(),
            scopes: array_map(fn ($scope) => $scope->value, $apiKey->scopes()),
            lastUsedAt: $apiKey->lastUsedAt()?->format(DATE_ATOM),
            expiresAt: $apiKey->expiresAt()?->format(DATE_ATOM),
            isRevoked: $apiKey->isRevoked(),
            createdAt: $apiKey->createdAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array{id: int, keyPrefix: string, label: ?string, scopes: list<string>, lastUsedAt: ?string, expiresAt: ?string, isRevoked: bool, createdAt: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'keyPrefix' => $this->keyPrefix,
            'label' => $this->label,
            'scopes' => $this->scopes,
            'lastUsedAt' => $this->lastUsedAt,
            'expiresAt' => $this->expiresAt,
            'isRevoked' => $this->isRevoked,
            'createdAt' => $this->createdAt,
        ];
    }
}
