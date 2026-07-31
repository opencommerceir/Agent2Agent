<?php

namespace App\Modules\Shipping\Domain\Services;

use App\Modules\Shipping\Application\DTOs\ProviderTrackingEventData;
use App\Modules\Shipping\Domain\Entities\Shipment;
use App\Modules\Shipping\Domain\ValueObjects\Address;
use App\Modules\Shipping\Domain\ValueObjects\ShippingRate;
use App\Modules\Shipping\Domain\ValueObjects\TrackingNumber;
use App\Modules\Shipping\Domain\ValueObjects\Weight;

/**
 * Base contract every Shipping provider implements — mirrors Commerce's
 * own `ConnectorInterface`/`ProductConnectorInterface` shape exactly (this
 * stage's explicit purpose is demonstrating that same Connector Pattern
 * inside Shipping). A provider's only job is communication + translation
 * into this module's own Domain types, never business rules (Connector
 * Conventions) — `ShippingProviderRegistry` is the seam that picks which
 * one runs, the same way `ConnectorRegistry` does for Commerce.
 */
interface ShippingProviderInterface
{
    /**
     * Identifies the provider for `ShippingProviderRegistry` lookups
     * (e.g. 'mock', 'usps').
     */
    public function getName(): string;

    /**
     * Lightweight health check — real providers ping their API; this is
     * the same seam Commerce's `ConnectorInterface::isConnected()`
     * already establishes.
     */
    public function isConnected(): bool;

    /**
     * @return list<ShippingRate>
     */
    public function getRates(Weight $weight, Address $destination): array;

    /**
     * Returns the provider's own tracking number/reference for the given
     * (already locally-created) Shipment. **Mock-only limitation, stated
     * plainly**: `Mock`'s own fixture happens to return `TRK-XXXXXXXX`-shaped
     * values, which is exactly `TrackingNumber`'s own strict format — so
     * this return type is honestly satisfiable today. A real provider
     * (USPS/FedEx/DHL) will almost certainly return a tracking number in
     * its own format, which this VO's regex would reject outright; that
     * future real implementation will need this loosened to a plain
     * `string` (see `Shipment::assignProviderTracking()`'s own docblock —
     * the caller already stores the provider's tracking number as a plain
     * string precisely because of this).
     */
    public function createShipment(Shipment $shipment): TrackingNumber;

    /**
     * Deliberately returns a provider-shaped DTO, never `TrackingEvent`
     * itself — `TrackingEvent::record()` requires the *local* `shipmentId`
     * (this module's own DB primary key), which a provider structurally
     * cannot know (it only ever sees a `TrackingNumber`). The same
     * reasoning `WooCommerceProductConnector` already demonstrates by
     * returning `UCPProduct` rather than Commerce's own persisted
     * `Product` entity. `SyncTrackingAction` (which does know the local
     * Shipment) is the one place these get turned into real
     * `TrackingEvent` entities.
     *
     * @return list<ProviderTrackingEventData>
     */
    public function getTrackingUpdates(TrackingNumber $trackingNumber): array;
}
