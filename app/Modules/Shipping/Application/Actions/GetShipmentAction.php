<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\ShipmentData;
use App\Modules\Shipping\Domain\Exceptions\ShipmentNotFoundException;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;

final class GetShipmentAction
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipments,
    ) {
    }

    public function execute(int $shipmentId, int $tenantId): ShipmentData
    {
        $shipment = $this->shipments->findById($shipmentId, $tenantId);

        if (! $shipment) {
            throw new ShipmentNotFoundException("Shipment [{$shipmentId}] does not exist.");
        }

        return ShipmentData::fromEntity($shipment);
    }
}
