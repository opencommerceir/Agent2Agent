<?php

namespace App\Modules\Finance\Application\DTOs;

use App\Modules\Finance\Domain\Entities\Invoice;

/**
 * Structured data transfer for Invoice across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class InvoiceData
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $orderId,
        public readonly ?int $customerId,
        public readonly string $invoiceNumber,
        public readonly string $status,
        public readonly array $items,
        public readonly int $subtotalAmount,
        public readonly string $subtotalCurrency,
        public readonly int $taxAmount,
        public readonly string $taxCurrency,
        public readonly int $totalAmount,
        public readonly string $totalCurrency,
        public readonly ?string $issuedAt,
    ) {
    }

    public static function fromEntity(Invoice $invoice): self
    {
        return new self(
            id: $invoice->id(),
            tenantId: $invoice->tenantId(),
            orderId: $invoice->orderId(),
            customerId: $invoice->customerId(),
            invoiceNumber: $invoice->invoiceNumber()->value(),
            status: $invoice->status()->value,
            items: array_map(
                fn ($item) => InvoiceItemData::fromEntity($item)->toArray(),
                $invoice->items(),
            ),
            subtotalAmount: $invoice->subtotal()->amount(),
            subtotalCurrency: $invoice->subtotal()->currency(),
            taxAmount: $invoice->tax()->amount(),
            taxCurrency: $invoice->tax()->currency(),
            totalAmount: $invoice->total()->amount(),
            totalCurrency: $invoice->total()->currency(),
            issuedAt: $invoice->issuedAt()?->format(DATE_ATOM),
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
            'orderId' => $this->orderId,
            'customerId' => $this->customerId,
            'invoiceNumber' => $this->invoiceNumber,
            'status' => $this->status,
            'items' => $this->items,
            'subtotalAmount' => $this->subtotalAmount,
            'subtotalCurrency' => $this->subtotalCurrency,
            'taxAmount' => $this->taxAmount,
            'taxCurrency' => $this->taxCurrency,
            'totalAmount' => $this->totalAmount,
            'totalCurrency' => $this->totalCurrency,
            'issuedAt' => $this->issuedAt,
        ];
    }
}
