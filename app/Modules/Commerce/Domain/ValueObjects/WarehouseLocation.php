<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A Warehouse's physical location — latitude/longitude for
 * WarehouseDistanceCalculator's Haversine math, plus a human-readable
 * address for display. Deliberately Commerce's own class, not shared with
 * Shipping's Address VO (which has no lat/lng at all) — the same
 * "depend on the other module's Repository Interface, never its concrete
 * Domain-layer class" reasoning Shipping's own Money/Address docblocks
 * already give for Commerce.
 */
final class WarehouseLocation
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $address,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException("Invalid latitude [{$latitude}]. Must be between -90 and 90.");
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException("Invalid longitude [{$longitude}]. Must be between -180 and 180.");
        }

        if (trim($address) === '') {
            throw new InvalidArgumentException('WarehouseLocation requires a non-empty address.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
            address: $data['address'],
        );
    }

    /**
     * @return array{latitude: float, longitude: float, address: string}
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
        ];
    }
}
