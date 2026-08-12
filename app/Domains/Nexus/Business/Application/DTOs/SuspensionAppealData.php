<?php

namespace App\Domains\Nexus\Business\Application\DTOs;

use App\Domains\Nexus\Business\Domain\Entities\SuspensionAppeal;

/**
 * Structured data transfer for SuspensionAppeal across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class SuspensionAppealData
{
    public function __construct(
        public readonly int $id,
        public readonly int $businessId,
        public readonly string $message,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly ?string $resolvedAt,
    ) {
    }

    public static function fromEntity(SuspensionAppeal $appeal): self
    {
        return new self(
            id: $appeal->id(),
            businessId: $appeal->businessId(),
            message: $appeal->message(),
            status: $appeal->status()->value,
            createdAt: $appeal->createdAt()->format(DATE_ATOM),
            resolvedAt: $appeal->resolvedAt()?->format(DATE_ATOM),
        );
    }

    /**
     * @return array{id: int, businessId: int, message: string, status: string, createdAt: string, resolvedAt: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'businessId' => $this->businessId,
            'message' => $this->message,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'resolvedAt' => $this->resolvedAt,
        ];
    }
}
