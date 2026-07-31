<?php

namespace App\Modules\Shipping\Application\DTOs;

use App\Modules\Shipping\Domain\Entities\Shipment;

/**
 * The result of `CreateProviderShipmentAction` — the local Shipment's id
 * plus the provider's own name/tracking number assigned to it
 * (`Shipment::assignProviderTracking()`).
 */
final class ProviderShipmentData
{
    public function __construct(
        public readonly int $shipmentId,
        public readonly string $provider,
        public readonly string $providerTrackingNumber,
    ) {
    }

    public static function fromEntity(Shipment $shipment): self
    {
        return new self(
            shipmentId: $shipment->id(),
            provider: $shipment->providerName(),
            providerTrackingNumber: $shipment->providerTrackingNumber(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'shipmentId' => $this->shipmentId,
            'provider' => $this->provider,
            'providerTrackingNumber' => $this->providerTrackingNumber,
        ];
    }
}
