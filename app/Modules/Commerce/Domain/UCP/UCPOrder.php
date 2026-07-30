<?php

namespace App\Modules\Commerce\Domain\UCP;

/**
 * `lineItems` is kept as a plain array in this skeleton rather than a
 * typed UCPOrderLine value object — designing that shape properly needs
 * real connector data (Shopify vs WooCommerce line items differ in ways
 * not worth guessing at yet) to validate against. Real Phase 2 work,
 * deliberately not invented prematurely here (KISS).
 */
final class UCPOrder
{
    /**
     * @param list<array<string, mixed>> $lineItems
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $sourceSystem,
        public readonly ?string $customerExternalId,
        public readonly string $status,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly array $lineItems = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['externalId'],
            sourceSystem: $data['sourceSystem'],
            customerExternalId: $data['customerExternalId'] ?? null,
            status: $data['status'],
            totalAmount: $data['totalAmount'],
            currency: $data['currency'],
            lineItems: $data['lineItems'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'externalId' => $this->externalId,
            'sourceSystem' => $this->sourceSystem,
            'customerExternalId' => $this->customerExternalId,
            'status' => $this->status,
            'totalAmount' => $this->totalAmount,
            'currency' => $this->currency,
            'lineItems' => $this->lineItems,
        ];
    }
}
