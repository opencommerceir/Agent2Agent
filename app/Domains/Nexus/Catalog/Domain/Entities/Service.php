<?php

namespace App\Domains\Nexus\Catalog\Domain\Entities;

use App\Domains\Nexus\Catalog\Domain\ValueObjects\Money;
use DateTimeImmutable;

/**
 * A time-based offering a Business sells (roadmap: "قیمت ساعتی، مدت،
 * زمان‌بندی"). $price is per-hour; $durationMinutes is the typical/default
 * booking length. Framework-free (Domain Layer Rules).
 */
final class Service
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private string $nameFa,
        private string $nameEn,
        private Money $hourlyPrice,
        private ?int $durationMinutes,
        private ?array $attributes,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function add(
        int $businessId,
        string $nameFa,
        string $nameEn,
        Money $hourlyPrice,
        ?int $durationMinutes = null,
        ?array $attributes = null,
    ): self {
        return new self(
            id: null,
            businessId: $businessId,
            nameFa: $nameFa,
            nameEn: $nameEn,
            hourlyPrice: $hourlyPrice,
            durationMinutes: $durationMinutes,
            attributes: $attributes,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function update(string $nameFa, string $nameEn, Money $hourlyPrice, ?int $durationMinutes, ?array $attributes): void
    {
        $this->nameFa = $nameFa;
        $this->nameEn = $nameEn;
        $this->hourlyPrice = $hourlyPrice;
        $this->durationMinutes = $durationMinutes;
        $this->attributes = $attributes;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function nameFa(): string
    {
        return $this->nameFa;
    }

    public function nameEn(): string
    {
        return $this->nameEn;
    }

    public function hourlyPrice(): Money
    {
        return $this->hourlyPrice;
    }

    public function durationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function attributes(): ?array
    {
        return $this->attributes;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
