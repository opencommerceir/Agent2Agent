<?php

namespace App\Modules\Analytics\Domain\Entities;

use App\Modules\Analytics\Domain\ValueObjects\Money;
use DateTimeImmutable;

/**
 * A daily rollup of the platform's own headline numbers for one Tenant —
 * what the scheduled `analytics:generate-snapshot` command (§7.18) writes
 * once per Tenant per day, and what the Dashboard Home page reads back
 * instead of recomputing every KPI on every page load. Immutable once
 * captured — a re-run for the same `snapshotDate` overwrites via
 * `AnalyticsSnapshotRepositoryInterface::save()`'s own upsert-by-date
 * behavior (see that Repository's own docblock), not a new mutator here.
 */
final class AnalyticsSnapshot
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly DateTimeImmutable $snapshotDate,
        private readonly Money $totalRevenue,
        private readonly int $totalOrders,
        private readonly int $totalCustomers,
        private readonly Money $avgOrderValue,
        private readonly ?float $conversionRate,
        private readonly array $topProducts,
        private readonly array $topCustomers,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function capture(
        int $tenantId,
        DateTimeImmutable $snapshotDate,
        Money $totalRevenue,
        int $totalOrders,
        int $totalCustomers,
        Money $avgOrderValue,
        ?float $conversionRate,
        array $topProducts,
        array $topCustomers,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            snapshotDate: $snapshotDate,
            totalRevenue: $totalRevenue,
            totalOrders: $totalOrders,
            totalCustomers: $totalCustomers,
            avgOrderValue: $avgOrderValue,
            conversionRate: $conversionRate,
            topProducts: $topProducts,
            topCustomers: $topCustomers,
            createdAt: new DateTimeImmutable(),
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

    public function snapshotDate(): DateTimeImmutable
    {
        return $this->snapshotDate;
    }

    public function totalRevenue(): Money
    {
        return $this->totalRevenue;
    }

    public function totalOrders(): int
    {
        return $this->totalOrders;
    }

    public function totalCustomers(): int
    {
        return $this->totalCustomers;
    }

    public function avgOrderValue(): Money
    {
        return $this->avgOrderValue;
    }

    public function conversionRate(): ?float
    {
        return $this->conversionRate;
    }

    public function topProducts(): array
    {
        return $this->topProducts;
    }

    public function topCustomers(): array
    {
        return $this->topCustomers;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
