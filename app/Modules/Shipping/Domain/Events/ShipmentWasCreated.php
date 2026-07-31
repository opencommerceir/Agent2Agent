<?php

namespace App\Modules\Shipping\Domain\Events;

use App\Modules\Shipping\Domain\Entities\Shipment;

/**
 * Domain event: a fact that already happened. Dispatched by
 * CreateShipmentAction after the Shipment has been persisted and the
 * owning Order updated. No registered Listener this stage — same
 * "dispatched, nothing reacts yet" shape most events in this codebase
 * have (only InventoryWasCommitted/OrderWasPlaced have real Listeners
 * so far, Workflows'/Loyalty's own).
 */
final class ShipmentWasCreated
{
    public function __construct(
        public readonly Shipment $shipment,
    ) {
    }
}
