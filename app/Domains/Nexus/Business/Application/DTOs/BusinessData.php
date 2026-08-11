<?php

namespace App\Domains\Nexus\Business\Application\DTOs;

use App\Domains\Nexus\Business\Domain\Entities\Business;

/**
 * Structured data transfer for Business across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class BusinessData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $organizationId,
        public readonly string $nameFa,
        public readonly string $nameEn,
        public readonly string $type,
        public readonly string $industry,
        public readonly string $verificationStatus,
        public readonly ?string $logoPath,
        public readonly ?array $documents,
    ) {
    }

    public static function fromEntity(Business $business): self
    {
        return new self(
            id: $business->id(),
            tenantId: $business->tenantId(),
            organizationId: $business->organizationId(),
            nameFa: $business->nameFa(),
            nameEn: $business->nameEn(),
            type: $business->type()->value,
            industry: $business->industry()->value,
            verificationStatus: $business->verificationStatus()->value,
            logoPath: $business->logoPath(),
            documents: $business->documents(),
        );
    }

    /**
     * @return array{id: ?int, tenantId: int, organizationId: int, nameFa: string, nameEn: string, type: string, industry: string, verificationStatus: string, logoPath: ?string, documents: ?array}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'organizationId' => $this->organizationId,
            'nameFa' => $this->nameFa,
            'nameEn' => $this->nameEn,
            'type' => $this->type,
            'industry' => $this->industry,
            'verificationStatus' => $this->verificationStatus,
            'logoPath' => $this->logoPath,
            'documents' => $this->documents,
        ];
    }
}
