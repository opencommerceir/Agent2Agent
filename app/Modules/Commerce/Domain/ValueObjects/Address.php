<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A flat data holder rather than a single-value wrapper (Money/SKU/
 * Quantity's shape) — same fromArray()/toArray() style
 * Domain\UCP\UCPProduct already established for this kind of
 * multi-field, framework-free VO. state/postalCode are nullable: not
 * every country's address has either.
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

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}
