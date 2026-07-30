<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\ValueObjects\Address;

final class AddressData
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly ?string $state,
        public readonly ?string $postalCode,
        public readonly string $country,
    ) {
    }

    public static function fromEntity(Address $address): self
    {
        return new self(
            street: $address->street,
            city: $address->city,
            state: $address->state,
            postalCode: $address->postalCode,
            country: $address->country,
        );
    }

    /**
     * @return array{street: string, city: string, state: ?string, postalCode: ?string, country: string}
     */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
        ];
    }
}
