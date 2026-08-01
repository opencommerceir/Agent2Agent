<?php

namespace App\Modules\Analytics\Application\DTOs;

use App\Modules\Analytics\Domain\Entities\KPIValue;

/**
 * `unit` doubles as the persisted `value_currency` column — for a
 * genuinely monetary KPI (Revenue, AverageOrderValue, CustomerLifetimeValue)
 * it's a real ISO currency code; for every other KPI it's a documented
 * placeholder tag `Money`'s own currency validation happens to accept
 * (3 uppercase letters): `PCT` (a percentage, `amount` scaled ×100 for
 * 2-decimal precision — HANDOFF gotcha #4, integers only, never a float
 * column), `CNT` (a plain count), `PTS` (loyalty points), or `LST` (a
 * 3-letter tag — `Money`'s own currency validation requires exactly 3
 * uppercase letters, so "LIST" itself doesn't fit; the real payload lives
 * in `metadata`, `amount` is a meaningless 0 — used by
 * `TopProducts`/`LowStockProducts`). See `CalculateKPIAction`'s own
 * docblock for the full mapping.
 */
final class KPIValueData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $kpiId,
        public readonly string $kpiType,
        public readonly int $amount,
        public readonly string $unit,
        public readonly string $timePeriod,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $calculatedAt,
        public readonly array $metadata,
    ) {
    }

    public static function fromEntity(KPIValue $value, string $kpiType): self
    {
        return new self(
            id: $value->id(),
            tenantId: $value->tenantId(),
            kpiId: $value->kpiId(),
            kpiType: $kpiType,
            amount: $value->value()->amount(),
            unit: $value->value()->currency(),
            timePeriod: $value->timePeriod()->value,
            periodStart: $value->periodStart()->format('Y-m-d'),
            periodEnd: $value->periodEnd()->format('Y-m-d'),
            calculatedAt: $value->calculatedAt()->format(DATE_ATOM),
            metadata: $value->metadata(),
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
            'kpiId' => $this->kpiId,
            'kpiType' => $this->kpiType,
            'amount' => $this->amount,
            'unit' => $this->unit,
            'timePeriod' => $this->timePeriod,
            'periodStart' => $this->periodStart,
            'periodEnd' => $this->periodEnd,
            'calculatedAt' => $this->calculatedAt,
            'metadata' => $this->metadata,
        ];
    }
}
