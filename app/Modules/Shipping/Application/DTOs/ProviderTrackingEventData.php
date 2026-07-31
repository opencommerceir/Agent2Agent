<?php

namespace App\Modules\Shipping\Application\DTOs;

use DateTimeImmutable;

/**
 * A raw tracking update as reported by an external provider — deliberately
 * not `TrackingEvent` (this module's own persisted entity), since a
 * provider has no concept of this module's internal `shipmentId`
 * (`ShippingProviderInterface::getTrackingUpdates()`'s own docblock has
 * the full reasoning). `SyncTrackingAction` is the one place these get
 * turned into real `TrackingEvent::record($shipment->id(), ...)` rows.
 */
final class ProviderTrackingEventData
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $location,
        public readonly string $description,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            location: $data['location'] ?? null,
            description: $data['description'],
            occurredAt: new DateTimeImmutable($data['timestamp']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'location' => $this->location,
            'description' => $this->description,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
        ];
    }
}
