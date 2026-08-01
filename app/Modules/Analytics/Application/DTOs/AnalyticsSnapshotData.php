<?php

namespace App\Modules\Analytics\Application\DTOs;

use App\Modules\Analytics\Domain\Entities\AnalyticsSnapshot;

/**
 * Not named in this stage's own request's DTO list, but every other
 * persisted Entity in this codebase gets a matching `*Data` DTO
 * (HANDOFF §3 pattern #12) — `analytics.snapshot.generate`'s own output
 * schema is just `{"snapshot": "object"}`, so this is what actually
 * shapes that object.
 */
final class AnalyticsSnapshotData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $snapshotDate,
        public readonly int $totalRevenueCents,
        public readonly string $currency,
        public readonly int $totalOrders,
        public readonly int $totalCustomers,
        public readonly int $avgOrderValueCents,
        public readonly ?float $conversionRatePercent,
        public readonly array $topProducts,
        public readonly array $topCustomers,
    ) {
    }

    public static function fromEntity(AnalyticsSnapshot $snapshot): self
    {
        return new self(
            id: $snapshot->id(),
            tenantId: $snapshot->tenantId(),
            snapshotDate: $snapshot->snapshotDate()->format('Y-m-d'),
            totalRevenueCents: $snapshot->totalRevenue()->amount(),
            currency: $snapshot->totalRevenue()->currency(),
            totalOrders: $snapshot->totalOrders(),
            totalCustomers: $snapshot->totalCustomers(),
            avgOrderValueCents: $snapshot->avgOrderValue()->amount(),
            conversionRatePercent: $snapshot->conversionRate(),
            topProducts: $snapshot->topProducts(),
            topCustomers: $snapshot->topCustomers(),
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
            'snapshotDate' => $this->snapshotDate,
            'totalRevenueCents' => $this->totalRevenueCents,
            'currency' => $this->currency,
            'totalOrders' => $this->totalOrders,
            'totalCustomers' => $this->totalCustomers,
            'avgOrderValueCents' => $this->avgOrderValueCents,
            'conversionRatePercent' => $this->conversionRatePercent,
            'topProducts' => $this->topProducts,
            'topCustomers' => $this->topCustomers,
        ];
    }
}
