<?php

namespace App\Modules\Finance\Application\DTOs;

use App\Modules\Finance\Domain\Entities\InvoiceItem;

/**
 * Structured data transfer for InvoiceItem across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class InvoiceItemData
{
    public function __construct(
        public readonly string $description,
        public readonly int $quantity,
        public readonly int $unitPriceAmount,
        public readonly string $unitPriceCurrency,
        public readonly int $totalAmount,
        public readonly string $totalCurrency,
    ) {
    }

    public static function fromEntity(InvoiceItem $item): self
    {
        return new self(
            description: $item->description(),
            quantity: $item->quantity(),
            unitPriceAmount: $item->unitPrice()->amount(),
            unitPriceCurrency: $item->unitPrice()->currency(),
            totalAmount: $item->totalAmount()->amount(),
            totalCurrency: $item->totalAmount()->currency(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitPriceAmount' => $this->unitPriceAmount,
            'unitPriceCurrency' => $this->unitPriceCurrency,
            'totalAmount' => $this->totalAmount,
            'totalCurrency' => $this->totalCurrency,
        ];
    }
}
