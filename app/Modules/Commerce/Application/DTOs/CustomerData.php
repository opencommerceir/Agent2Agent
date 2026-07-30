<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\Customer;

final class CustomerData
{
    /**
     * @param ?array{street: string, city: string, state: ?string, postalCode: ?string, country: string} $defaultAddress
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $status,
        public readonly ?array $defaultAddress,
        public readonly ?string $notes,
    ) {
    }

    public static function fromEntity(Customer $customer): self
    {
        $address = $customer->defaultAddress();

        return new self(
            id: $customer->id(),
            tenantId: $customer->tenantId(),
            firstName: $customer->firstName(),
            lastName: $customer->lastName(),
            email: $customer->email()->value(),
            phone: $customer->phone(),
            status: $customer->status()->value,
            defaultAddress: $address ? AddressData::fromEntity($address)->toArray() : null,
            notes: $customer->notes(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'defaultAddress' => $this->defaultAddress,
            'notes' => $this->notes,
        ];
    }
}
