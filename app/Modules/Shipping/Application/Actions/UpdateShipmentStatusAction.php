<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\ShipmentData;
use App\Modules\Shipping\Domain\Events\ShipmentStatusChanged;
use App\Modules\Shipping\Domain\Exceptions\ShipmentNotFoundException;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;
use App\Modules\Shipping\Domain\ValueObjects\TrackingStatus;
use Illuminate\Support\Facades\Event;

/**
 * The authoritative state-machine transition (Shipment::changeStatus()'s
 * own docblock) — distinct from AddTrackingEventAction, which only
 * appends a log entry and never touches this field.
 */
final class UpdateShipmentStatusAction
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipments,
    ) {
    }

    public function execute(int $tenantId, int $shipmentId, string $status): ShipmentData
    {
        $shipment = $this->shipments->findById($shipmentId, $tenantId);

        if (! $shipment) {
            throw new ShipmentNotFoundException("Shipment [{$shipmentId}] does not exist.");
        }

        $previousStatus = $shipment->status();

        $shipment->changeStatus(TrackingStatus::from($status));
        $shipment = $this->shipments->save($shipment);

        Event::dispatch(new ShipmentStatusChanged($shipment, $previousStatus));

        return ShipmentData::fromEntity($shipment);
    }
}
