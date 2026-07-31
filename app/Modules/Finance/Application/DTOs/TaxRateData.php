<?php

namespace App\Modules\Finance\Application\DTOs;

use App\Modules\Finance\Domain\Entities\TaxRate;

/**
 * Structured data transfer for TaxRate across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class TaxRateData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $region,
        public readonly int $ratePercentage,
        public readonly bool $isActive,
    ) {
    }

    public static function fromEntity(TaxRate $taxRate): self
    {
        return new self(
            id: $taxRate->id(),
            tenantId: $taxRate->tenantId(),
            region: $taxRate->region()->value(),
            ratePercentage: $taxRate->ratePercentage(),
            isActive: $taxRate->isActive(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'region' => $this->region,
            'ratePercentage' => $this->ratePercentage,
            'isActive' => $this->isActive,
        ];
    }
}
