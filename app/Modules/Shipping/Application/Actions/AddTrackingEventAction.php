<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\TrackingEventData;
use App\Modules\Shipping\Domain\Entities\TrackingEvent;
use App\Modules\Shipping\Domain\Events\TrackingEventWasAdded;
use App\Modules\Shipping\Domain\Exceptions\ShipmentNotFoundException;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;
use App\Modules\Shipping\Domain\ValueObjects\TrackingStatus;
use Illuminate\Support\Facades\Event;

/**
 * Appends one entry to a Shipment's tracking history. Deliberately does
 * NOT call Shipment::changeStatus() — see TrackingEvent's own docblock
 * for why a log entry and the Shipment's authoritative current status
 * are kept independent.
 */
final class AddTrackingEventAction
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipments,
    ) {
    }

    public function execute(
        int $tenantId,
        int $shipmentId,
        string $status,
        string $description,
        ?string $location = null,
    ): TrackingEventData {
        $shipment = $this->shipments->findById($shipmentId, $tenantId);

        if (! $shipment) {
            throw new ShipmentNotFoundException("Shipment [{$shipmentId}] does not exist.");
        }

        $event = TrackingEvent::record($shipmentId, TrackingStatus::from($status), $description, $location);
        $event = $this->shipments->saveTrackingEvent($event);

        Event::dispatch(new TrackingEventWasAdded($event));

        return TrackingEventData::fromEntity($event);
    }
}
