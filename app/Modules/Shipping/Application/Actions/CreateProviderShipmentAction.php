<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\ProviderShipmentData;
use App\Modules\Shipping\Application\Services\ShippingProviderRegistry;
use App\Modules\Shipping\Domain\Exceptions\ShipmentNotFoundException;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;

/**
 * Hands an already-created local Shipment (Stage 1's `CreateShipmentAction`)
 * off to an external provider: resolves the provider, calls its own
 * `createShipment()`, then records the provider's own name/tracking
 * number onto the Shipment via `Shipment::assignProviderTracking()` —
 * that entity's own docblock has the full reasoning for why this is a
 * separate field from Shipping's own internal `trackingNumber`.
 */
final class CreateProviderShipmentAction
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipments,
        private readonly ShippingProviderRegistry $providers,
    ) {
    }

    public function execute(int $tenantId, string $providerName, int $shipmentId): ProviderShipmentData
    {
        $shipment = $this->shipments->findById($shipmentId, $tenantId);

        if (! $shipment) {
            throw new ShipmentNotFoundException("Shipment [{$shipmentId}] does not exist.");
        }

        $provider = $this->providers->get($providerName);
        $providerTrackingNumber = $provider->createShipment($shipment);

        $shipment->assignProviderTracking($providerName, $providerTrackingNumber->value());
        $shipment = $this->shipments->save($shipment);

        return ProviderShipmentData::fromEntity($shipment);
    }
}
