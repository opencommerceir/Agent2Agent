<?php

namespace App\Modules\Shipping\Domain\Repositories;

use App\Modules\Shipping\Domain\Entities\Shipment;
use App\Modules\Shipping\Domain\Entities\TrackingEvent;
use App\Modules\Shipping\Domain\ValueObjects\TrackingStatus;

/**
 * Contract owned by the Domain layer (Interfaces Over Tight Coupling).
 * Every method takes tenantId explicitly — never inferred from ambient
 * state. Also owns TrackingEvent persistence (saveTrackingEvent()/
 * listTrackingEvents()) — an event has no meaning detached from the
 * Shipment it tracks, the same "repository interface owns its child
 * records" shape every prior module's own repository already
 * establishes (Workflows' WorkflowLog, Loyalty's Redemption, Finance's
 * InvoiceItem, ...).
 */
interface ShipmentRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Shipment;

    public function trackingNumberExists(string $trackingNumber, int $tenantId): bool;

    /**
     * Added for `shipping.tracking.sync` (Phase 4 Stage 2): the capability
     * takes a `tracking_number`, not a `shipment_id` — nothing before this
     * could look a Shipment up by anything other than its own id.
     */
    public function findByTrackingNumber(string $trackingNumber, int $tenantId): ?Shipment;

    /**
     * @return list<Shipment>
     */
    public function list(int $tenantId, ?TrackingStatus $status, ?int $orderId, int $limit): array;

    public function save(Shipment $shipment): Shipment;

    public function saveTrackingEvent(TrackingEvent $event): TrackingEvent;

    /**
     * @return list<TrackingEvent>
     */
    public function listTrackingEvents(int $shipmentId, int $tenantId): array;
}
