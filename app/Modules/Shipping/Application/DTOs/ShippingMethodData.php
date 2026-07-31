<?php

namespace App\Modules\Shipping\Application\DTOs;

use App\Modules\Shipping\Domain\Entities\ShippingMethod;

final class ShippingMethodData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $baseRateAmount,
        public readonly string $baseRateCurrency,
        public readonly int $ratePerKgAmount,
        public readonly string $ratePerKgCurrency,
        public readonly int $estimatedDaysMin,
        public readonly int $estimatedDaysMax,
        public readonly bool $isActive,
    ) {
    }

    public static function fromEntity(ShippingMethod $method): self
    {
        return new self(
            id: $method->id(),
            tenantId: $method->tenantId(),
            name: $method->name(),
            description: $method->description(),
            baseRateAmount: $method->baseRate()->amount(),
            baseRateCurrency: $method->baseRate()->currency(),
            ratePerKgAmount: $method->ratePerKg()->amount(),
            ratePerKgCurrency: $method->ratePerKg()->currency(),
            estimatedDaysMin: $method->estimatedDaysMin(),
            estimatedDaysMax: $method->estimatedDaysMax(),
            isActive: $method->isActive(),
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
            'name' => $this->name,
            'description' => $this->description,
            'baseRateAmount' => $this->baseRateAmount,
            'baseRateCurrency' => $this->baseRateCurrency,
            'ratePerKgAmount' => $this->ratePerKgAmount,
            'ratePerKgCurrency' => $this->ratePerKgCurrency,
            'estimatedDaysMin' => $this->estimatedDaysMin,
            'estimatedDaysMax' => $this->estimatedDaysMax,
            'isActive' => $this->isActive,
        ];
    }
}
