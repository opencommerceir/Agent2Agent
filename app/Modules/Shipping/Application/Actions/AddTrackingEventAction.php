<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\TrackingEventData;
use App\Modules\Shipping\Domain\Entities\TrackingEvent;
use App\Modules\Shipping\Domain\Events\TrackingEventWasAdded;
use App\Modules\Shipping\Domain\Exceptions\ShipmentNotFoundException;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;
use App\Modules\Shipping\Domain\ValueObjects\TrackingStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;

/**
 * Appends one entry to a Shipment's tracking history. Deliberately does
 * NOT call Shipment::changeStatus() — see TrackingEvent's own docblock
 * for why a log entry and the Shipment's authoritative current status
 * are kept independent.
 *
 * `occurredAt` is optional (Phase 4 Stage 2) — omitted (the
 * `shipping.tracking.add` capability's own case, an Agent reporting an
 * update as it happens) it defaults to now, same as before; `SyncTrackingAction`
 * is the one caller that supplies a real value, since a provider's own
 * tracking update carries its own historical timestamp, not "now" (HANDOFF
 * §3 pattern #6 — widen with an optional trailing parameter).
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
        ?DateTimeImmutable $occurredAt = null,
    ): TrackingEventData {
        $shipment = $this->shipments->findById($shipmentId, $tenantId);

        if (! $shipment) {
            throw new ShipmentNotFoundException("Shipment [{$shipmentId}] does not exist.");
        }

        $event = TrackingEvent::record($shipmentId, TrackingStatus::from($status), $description, $location, $occurredAt);
        $event = $this->shipments->saveTrackingEvent($event);

        Event::dispatch(new TrackingEventWasAdded($event));

        return TrackingEventData::fromEntity($event);
    }
}
