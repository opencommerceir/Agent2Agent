<?php

namespace App\Modules\Finance\Domain\Entities;

use App\Modules\Finance\Domain\ValueObjects\TaxRegion;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A tenant-configured tax rate for one TaxRegion (or the reserved
 * TaxRegion::default() fallback). ratePercentage is the percentage times
 * 100 (9.00% -> 900) — the same "Money as Integer" reasoning applied to a
 * rate instead of an amount, so no float-typed tax value ever exists
 * (unlike Commerce's own ValueObjects\TaxRate, which is a plain 0-100
 * float — that VO models a transient calculation input, not a persisted,
 * per-region configuration row, so the two are not interchangeable
 * despite the shared name).
 */
final class TaxRate
{
    private const MAX_RATE_PERCENTAGE = 10000; // 100.00%

    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly TaxRegion $region,
        private int $ratePercentage,
        private bool $isActive,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(int $tenantId, TaxRegion $region, int $ratePercentage): self
    {
        self::assertValidRate($ratePercentage);

        return new self(
            id: null,
            tenantId: $tenantId,
            region: $region,
            ratePercentage: $ratePercentage,
            isActive: true,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function update(int $ratePercentage, bool $isActive): void
    {
        self::assertValidRate($ratePercentage);

        $this->ratePercentage = $ratePercentage;
        $this->isActive = $isActive;
    }

    private static function assertValidRate(int $ratePercentage): void
    {
        if ($ratePercentage < 0 || $ratePercentage > self::MAX_RATE_PERCENTAGE) {
            throw new InvalidArgumentException(
                "Tax rate percentage must be between 0 and ".self::MAX_RATE_PERCENTAGE." (0%-100%), got [{$ratePercentage}]."
            );
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function region(): TaxRegion
    {
        return $this->region;
    }

    public function ratePercentage(): int
    {
        return $this->ratePercentage;
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
