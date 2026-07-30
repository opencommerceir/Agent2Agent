<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\Order;

final class OrderData
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $agentId,
        public readonly ?int $customerId,
        public readonly string $orderNumber,
        public readonly string $status,
        public readonly array $items,
        public readonly int $subtotalAmount,
        public readonly string $subtotalCurrency,
        public readonly int $taxAmount,
        public readonly int $discountAmount,
        public readonly int $totalAmount,
        public readonly string $totalCurrency,
        public readonly ?string $notes,
    ) {
    }

    public static function fromEntity(Order $order): self
    {
        return new self(
            id: $order->id(),
            tenantId: $order->tenantId(),
            agentId: $order->agentId(),
            customerId: $order->customerId(),
            orderNumber: $order->orderNumber()->value(),
            status: $order->status()->value,
            items: array_map(
                fn ($item) => OrderItemData::fromEntity($item)->toArray(),
                $order->items(),
            ),
            subtotalAmount: $order->subtotal()->amount(),
            subtotalCurrency: $order->subtotal()->currency(),
            taxAmount: $order->tax()->amount(),
            discountAmount: $order->discount()->amount(),
            totalAmount: $order->total()->amount(),
            totalCurrency: $order->total()->currency(),
            notes: $order->notes(),
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
            'agentId' => $this->agentId,
            'customerId' => $this->customerId,
            'orderNumber' => $this->orderNumber,
            'status' => $this->status,
            'items' => $this->items,
            'subtotalAmount' => $this->subtotalAmount,
            'subtotalCurrency' => $this->subtotalCurrency,
            'taxAmount' => $this->taxAmount,
            'discountAmount' => $this->discountAmount,
            'totalAmount' => $this->totalAmount,
            'totalCurrency' => $this->totalCurrency,
            'notes' => $this->notes,
        ];
    }
}
