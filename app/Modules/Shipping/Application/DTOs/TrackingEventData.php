<?php

namespace App\Modules\Shipping\Application\DTOs;

use App\Modules\Shipping\Domain\Entities\TrackingEvent;

final class TrackingEventData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $shipmentId,
        public readonly string $status,
        public readonly ?string $location,
        public readonly string $description,
        public readonly string $occurredAt,
    ) {
    }

    public static function fromEntity(TrackingEvent $event): self
    {
        return new self(
            id: $event->id(),
            shipmentId: $event->shipmentId(),
            status: $event->status()->value,
            location: $event->location(),
            description: $event->description(),
            occurredAt: $event->occurredAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'shipmentId' => $this->shipmentId,
            'status' => $this->status,
            'location' => $this->location,
            'description' => $this->description,
            'occurredAt' => $this->occurredAt,
        ];
    }
}
