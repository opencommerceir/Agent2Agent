<?php

namespace App\Modules\Shipping\Domain\Entities;

use App\Modules\Shipping\Domain\ValueObjects\Money;
use DateTimeImmutable;

/**
 * A tenant-defined carrier/service tier (e.g. "Standard", "Express").
 * No update/deactivate method exists this stage — not requested, same
 * "structure is frozen, no editing yet" shape Loyalty's Reward has
 * (that Entity's own docblock).
 */
final class ShippingMethod
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly string $name,
        private readonly ?string $description,
        private readonly Money $baseRate,
        private readonly Money $ratePerKg,
        private readonly int $estimatedDaysMin,
        private readonly int $estimatedDaysMax,
        private readonly bool $isActive,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $tenantId,
        string $name,
        ?string $description,
        Money $baseRate,
        Money $ratePerKg,
        int $estimatedDaysMin,
        int $estimatedDaysMax,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            name: $name,
            description: $description,
            baseRate: $baseRate,
            ratePerKg: $ratePerKg,
            estimatedDaysMin: $estimatedDaysMin,
            estimatedDaysMax: $estimatedDaysMax,
            isActive: true,
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

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function baseRate(): Money
    {
        return $this->baseRate;
    }

    public function ratePerKg(): Money
    {
        return $this->ratePerKg;
    }

    public function estimatedDaysMin(): int
    {
        return $this->estimatedDaysMin;
    }

    public function estimatedDaysMax(): int
    {
        return $this->estimatedDaysMax;
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
