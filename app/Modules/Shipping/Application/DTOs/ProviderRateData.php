<?php

namespace App\Modules\Shipping\Application\DTOs;

use App\Modules\Shipping\Domain\ValueObjects\ShippingRate;

/**
 * Wraps a provider-quoted `ShippingRate` (serviceName/serviceCode
 * populated, unlike the local-calculator path's `ShippingRateData`) for
 * `shipping.provider.rates`'s MCP output.
 */
final class ProviderRateData
{
    public function __construct(
        public readonly ?string $serviceName,
        public readonly ?string $serviceCode,
        public readonly int $costAmount,
        public readonly string $costCurrency,
        public readonly int $estimatedDaysMin,
        public readonly int $estimatedDaysMax,
    ) {
    }

    public static function fromValueObject(ShippingRate $rate): self
    {
        return new self(
            serviceName: $rate->serviceName(),
            serviceCode: $rate->serviceCode(),
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
            'serviceName' => $this->serviceName,
            'serviceCode' => $this->serviceCode,
            'costAmount' => $this->costAmount,
            'costCurrency' => $this->costCurrency,
            'estimatedDaysMin' => $this->estimatedDaysMin,
            'estimatedDaysMax' => $this->estimatedDaysMax,
        ];
    }
}
