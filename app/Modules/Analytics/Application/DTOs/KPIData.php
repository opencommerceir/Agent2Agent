<?php

namespace App\Modules\Analytics\Application\DTOs;

use App\Modules\Analytics\Domain\Entities\KPI;

final class KPIData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $type,
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $calculationFormula,
        public readonly bool $isActive,
    ) {
    }

    public static function fromEntity(KPI $kpi): self
    {
        return new self(
            id: $kpi->id(),
            tenantId: $kpi->tenantId(),
            type: $kpi->type()->value,
            name: $kpi->name(),
            description: $kpi->description(),
            calculationFormula: $kpi->calculationFormula(),
            isActive: $kpi->isActive(),
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
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'calculationFormula' => $this->calculationFormula,
            'isActive' => $this->isActive,
        ];
    }
}
