<?php

namespace App\Domains\Nexus\Developer\Application\DTOs;

use App\Domains\Nexus\Developer\Domain\Entities\IntegrationConnection;

/**
 * Structured data transfer for an integration connection. Deliberately
 * never carries authToken — represents data only, no business logic (DTO
 * Conventions).
 */
final class IntegrationConnectionData
{
    /**
     * @param array<string, string> $fieldMapping
     */
    public function __construct(
        public readonly int $id,
        public readonly string $category,
        public readonly string $name,
        public readonly string $targetUrl,
        public readonly array $fieldMapping,
        public readonly bool $isRevoked,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(IntegrationConnection $connection): self
    {
        return new self(
            id: $connection->id(),
            category: $connection->category()->value,
            name: $connection->name(),
            targetUrl: $connection->targetUrl(),
            fieldMapping: $connection->fieldMapping(),
            isRevoked: $connection->isRevoked(),
            createdAt: $connection->createdAt()->format(DATE_ATOM),
        );
    }
}
