<?php

namespace App\Modules\Analytics\Domain\Entities;

use App\Modules\Analytics\Domain\ValueObjects\Money;
use App\Modules\Analytics\Domain\ValueObjects\TimePeriod;
use DateTimeImmutable;

/**
 * One computed result for one KPI, for one period — immutable once
 * recorded (no update method), the same "immutable ledger row" shape
 * `PointTransaction`/`WorkflowLog` already establish. Owned by
 * `KPIRepositoryInterface` (`saveValue()`/`listValues()`), not a separate
 * Repository interface — the same "repo owns its child records"
 * convention `WorkflowRepositoryInterface`/`LoyaltyAccountRepositoryInterface`
 * already use for `WorkflowLog`/`Redemption`.
 *
 * The request's own file list also named a `Domain/ValueObjects/KPIValue.php`
 * alongside this Entity — the same class name in two layers. Since the
 * migration this stage requested (`kpi_values`: id, tenant_id, kpi_id, ...)
 * is unambiguously a persisted, identity-bearing row, not a small
 * immutable value, only the Entity was built; the VO version would have
 * been pure duplication with no distinct purpose (the same kind of
 * same-name-two-layers clash Phase 4 Stage 4's `TranslationService`
 * request had, HANDOFF §7.16 — resolved the same way, by keeping exactly
 * one).
 */
final class KPIValue
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $kpiId,
        private readonly Money $value,
        private readonly TimePeriod $timePeriod,
        private readonly DateTimeImmutable $periodStart,
        private readonly DateTimeImmutable $periodEnd,
        private readonly DateTimeImmutable $calculatedAt,
        private readonly array $metadata,
    ) {
    }

    public static function record(
        int $tenantId,
        int $kpiId,
        Money $value,
        TimePeriod $timePeriod,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        array $metadata = [],
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            kpiId: $kpiId,
            value: $value,
            timePeriod: $timePeriod,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            calculatedAt: new DateTimeImmutable(),
            metadata: $metadata,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function kpiId(): int
    {
        return $this->kpiId;
    }

    public function value(): Money
    {
        return $this->value;
    }

    public function timePeriod(): TimePeriod
    {
        return $this->timePeriod;
    }

    public function periodStart(): DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function periodEnd(): DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function calculatedAt(): DateTimeImmutable
    {
        return $this->calculatedAt;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }
}
