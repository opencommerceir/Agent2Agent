<?php

namespace App\Modules\Commerce\Domain\UCP;

final class UCPCustomer
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $sourceSystem,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['externalId'],
            sourceSystem: $data['sourceSystem'],
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'externalId' => $this->externalId,
            'sourceSystem' => $this->sourceSystem,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
