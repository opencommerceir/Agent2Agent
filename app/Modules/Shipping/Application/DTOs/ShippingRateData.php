<?php

namespace App\Modules\Shipping\Application\DTOs;

use App\Modules\Shipping\Domain\ValueObjects\ShippingRate;

final class ShippingRateData
{
    public function __construct(
        public readonly int $costAmount,
        public readonly string $costCurrency,
        public readonly int $estimatedDaysMin,
        public readonly int $estimatedDaysMax,
    ) {
    }

    public static function fromValueObject(ShippingRate $rate): self
    {
        return new self(
            costAmount: $rate->cost()->amount(),
            costCurrency: $rate->cost()->currency(),
            estimatedDaysMin: $rate->estimatedDaysMin(),
            estimatedDaysMax: $rate->estimatedDaysMax(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'costAmount' => $this->costAmount,
            'costCurrency' => $this->costCurrency,
            'estimatedDaysMin' => $this->estimatedDaysMin,
            'estimatedDaysMax' => $this->estimatedDaysMax,
        ];
    }
}
