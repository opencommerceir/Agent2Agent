<?php

namespace App\Modules\Analytics\Domain\Entities;

use App\Modules\Analytics\Domain\ValueObjects\KPIType;
use DateTimeImmutable;

/**
 * The saved *definition* of a KPI a tenant tracks — name, type, and an
 * opaque `calculationFormula` bag — mirrors the "parent definition, child
 * result" split Reporting's own `Report`/`ReportResult` and Workflows'
 * `Workflow`/`WorkflowLog` already establish: `KPI` is the definition,
 * `KPIValue` (below) is one computed result for one period.
 *
 * `calculationFormula` is schema-ready, not a real expression engine —
 * nothing in this stage parses or evaluates it; the actual math per
 * `KPIType` is fixed Domain Service logic (`KPICalculatorInterface`'s
 * implementations, or a direct Query Builder read for the pass-through
 * types). Storing it lets a tenant document *which* parameters a given
 * KPI definition uses (e.g. `{"limit": 5}` for a Top Products KPI)
 * without pretending this stage built a user-configurable formula
 * language — the same "field exists, schema-ready, nothing consumes it
 * yet" shape `ReportResult.expires_at` already has (HANDOFF §8.31).
 */
final class KPI
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly KPIType $type,
        private string $name,
        private ?string $description,
        private array $calculationFormula,
        private bool $isActive,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function define(
        int $tenantId,
        KPIType $type,
        string $name,
        ?string $description = null,
        array $calculationFormula = [],
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            type: $type,
            name: $name,
            description: $description,
            calculationFormula: $calculationFormula,
            isActive: true,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function rename(string $name, ?string $description): void
    {
        $this->name = $name;
        $this->description = $description;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function type(): KPIType
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function calculationFormula(): array
    {
        return $this->calculationFormula;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
