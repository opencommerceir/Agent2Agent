<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\TrackingEventData;
use App\Modules\Shipping\Application\Services\ShippingProviderRegistry;
use App\Modules\Shipping\Domain\Exceptions\ShipmentNotFoundException;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;
use InvalidArgumentException;

/**
 * Pulls tracking history from an external provider and folds in whatever
 * is genuinely new. Dedupe is by `(status, occurredAt)` pair against
 * `listTrackingEvents()` — no external event id exists to dedupe by more
 * precisely (`ShippingProviderInterface::getTrackingUpdates()`'s own
 * docblock explains why a provider event carries no local identity at
 * all), and a provider's own timestamps are precise enough in practice.
 *
 * After adding the new events, the *newest* one's status is applied via
 * the existing `UpdateShipmentStatusAction` — but only if it's a legal
 * transition. An illegal one (including "already this status") is caught
 * and skipped silently rather than failing the whole sync: a re-sync must
 * be idempotent, and a provider replaying/reordering its own event
 * history is normal, not an error.
 */
final class SyncTrackingAction
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipments,
        private readonly ShippingProviderRegistry $providers,
        private readonly AddTrackingEventAction $addTrackingEvent,
        private readonly UpdateShipmentStatusAction $updateShipmentStatus,
    ) {
    }

    /**
     * @return array{events: list<array<string, mixed>>, synced_count: int}
     */
    public function execute(int $tenantId, string $providerName, string $trackingNumber): array
    {
        $shipment = $this->shipments->findByTrackingNumber($trackingNumber, $tenantId);

        if (! $shipment) {
            throw new ShipmentNotFoundException("Shipment with tracking number [{$trackingNumber}] does not exist.");
        }

        $provider = $this->providers->get($providerName);
        $updates = $provider->getTrackingUpdates($shipment->trackingNumber());

        $existing = $this->shipments->listTrackingEvents($shipment->id(), $tenantId);
        $existingKeys = array_map(
            fn ($event) => $event->status()->value.'|'.$event->occurredAt()->format(DATE_ATOM),
            $existing,
        );

        $added = [];
        $lastNewStatus = null;

        foreach ($updates as $update) {
            $key = $update->status.'|'.$update->occurredAt->format(DATE_ATOM);

            if (in_array($key, $existingKeys, true)) {
                continue;
            }

            $event = $this->addTrackingEvent->execute(
                tenantId: $tenantId,
                shipmentId: $shipment->id(),
                status: $update->status,
                description: $update->description,
                location: $update->location,
                occurredAt: $update->occurredAt,
            );

            $added[] = $event;
            $lastNewStatus = $update->status;
        }

        if ($lastNewStatus !== null) {
            try {
                $this->updateShipmentStatus->execute($tenantId, $shipment->id(), $lastNewStatus);
            } catch (InvalidArgumentException) {
                // Same status, or an illegal transition — a re-sync must
                // stay idempotent; see this Action's own docblock.
            }
        }

        return [
            'events' => array_map(fn (TrackingEventData $event) => $event->toArray(), $added),
            'synced_count' => count($added),
        ];
    }
}
