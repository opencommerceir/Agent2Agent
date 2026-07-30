<?php

namespace App\Modules\Commerce\Domain\UCP;

final class UCPCart
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $sourceSystem,
        public readonly ?string $customerExternalId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly array $items = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['externalId'],
            sourceSystem: $data['sourceSystem'],
            customerExternalId: $data['customerExternalId'] ?? null,
            totalAmount: $data['totalAmount'],
            currency: $data['currency'],
            items: $data['items'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'externalId' => $this->externalId,
            'sourceSystem' => $this->sourceSystem,
            'customerExternalId' => $this->customerExternalId,
            'totalAmount' => $this->totalAmount,
            'currency' => $this->currency,
            'items' => $this->items,
        ];
    }
}
