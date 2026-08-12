<?php

namespace App\Domains\Nexus\Contract\Application\DTOs;

use App\Domains\Nexus\Contract\Domain\Entities\Contract;

/**
 * Structured data transfer for Contract across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class ContractData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $negotiationId,
        public readonly int $businessAId,
        public readonly int $businessBId,
        public readonly array $terms,
        public readonly string $contentHash,
        public readonly ?string $pdfPath,
    ) {
    }

    public static function fromEntity(Contract $contract): self
    {
        return new self(
            id: $contract->id(),
            negotiationId: $contract->negotiationId(),
            businessAId: $contract->businessAId(),
            businessBId: $contract->businessBId(),
            terms: $contract->terms(),
            contentHash: $contract->contentHash(),
            pdfPath: $contract->pdfPath(),
        );
    }

    /**
     * @return array{id: ?int, negotiationId: int, businessAId: int, businessBId: int, terms: array, contentHash: string, pdfPath: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'negotiationId' => $this->negotiationId,
            'businessAId' => $this->businessAId,
            'businessBId' => $this->businessBId,
            'terms' => $this->terms,
            'contentHash' => $this->contentHash,
            'pdfPath' => $this->pdfPath,
        ];
    }
}
