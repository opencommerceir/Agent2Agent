<?php

namespace App\Modules\Shipping\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Shipping's own Address — deliberately a second, separate class from
 * Commerce's `Address`, not a shared/reused one, the exact same reasoning
 * Shipping's own `Money` docblock already gives (HANDOFF §7.12/§7.8):
 * depending on Commerce's Repository *Interfaces* is fine (Dependency
 * Inversion), but importing Commerce's concrete `Address` VO would be a
 * direct Domain-layer dependency on another module's class. Only exists
 * to give `ShippingProviderInterface::getRates()` a destination to quote
 * against — a first, narrow step on HANDOFF §8.37's "no Address concept
 * anywhere in Shipping" gap, not a full Shipping Zones feature.
 */
final class Address
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly ?string $state,
        public readonly ?string $postalCode,
        public readonly string $country,
    ) {
        if (trim($street) === '' || trim($city) === '' || trim($country) === '') {
            throw new InvalidArgumentException('Address requires at least street, city, and country.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            street: $data['street'],
            city: $data['city'],
            state: $data['state'] ?? null,
            postalCode: $data['postalCode'] ?? $data['postal_code'] ?? null,
            country: $data['country'],
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
