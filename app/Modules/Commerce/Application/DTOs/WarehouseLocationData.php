<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;

final class WarehouseLocationData
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $address,
    ) {
    }

    public static function fromValueObject(WarehouseLocation $location): self
    {
        return new self($location->latitude, $location->longitude, $location->address);
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
