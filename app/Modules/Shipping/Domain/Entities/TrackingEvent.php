<?php

namespace App\Modules\Shipping\Domain\Entities;

use App\Modules\Shipping\Domain\ValueObjects\TrackingStatus;
use DateTimeImmutable;

/**
 * One immutable entry in a Shipment's tracking history — written once
 * by AddTrackingEventAction, never edited (same shape Loyalty's
 * PointTransaction/Workflows' WorkflowLog already establish for a
 * write-once audit record). No `tenant_id` of its own — inherited
 * through `shipment_id`, the same shape OrderItem/TicketComment have
 * relative to their own parent (HANDOFF gotcha #10 territory).
 *
 * Deliberately does NOT change the owning Shipment's own `status` field
 * — a TrackingEvent is a log entry a carrier reports (e.g. "arrived at
 * sorting facility"), while `Shipment.status` is the authoritative
 * current state, changed only through UpdateShipmentStatusAction's own
 * transition validation (Shipment::changeStatus()'s own docblock). This
 * keeps the two independent: logging many intermediate carrier updates
 * never has to also satisfy the Shipment's own state-machine rules.
 */
final class TrackingEvent
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $shipmentId,
        private readonly TrackingStatus $status,
        private readonly ?string $location,
        private readonly string $description,
        private readonly DateTimeImmutable $occurredAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        int $shipmentId,
        TrackingStatus $status,
        string $description,
        ?string $location = null,
        ?DateTimeImmutable $occurredAt = null,
    ): self {
        return new self(
            id: null,
            shipmentId: $shipmentId,
            status: $status,
            location: $location,
            description: $description,
            occurredAt: $occurredAt ?? new DateTimeImmutable(),
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function shipmentId(): int
    {
        return $this->shipmentId;
    }

    public function status(): TrackingStatus
    {
        return $this->status;
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
