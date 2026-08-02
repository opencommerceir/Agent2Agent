<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\OrderItem;

final class OrderItemData
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly int $unitPriceAmount,
        public readonly string $unitPriceCurrency,
        public readonly int $totalPriceAmount,
        public readonly string $totalPriceCurrency,
        public readonly ?int $variantId = null,
    ) {
    }

    public static function fromEntity(OrderItem $item): self
    {
        return new self(
            productId: $item->productId(),
            quantity: $item->quantity()->value(),
            unitPriceAmount: $item->unitPrice()->amount(),
            unitPriceCurrency: $item->unitPrice()->currency(),
            totalPriceAmount: $item->totalAmount(),
            totalPriceCurrency: $item->unitPrice()->currency(),
            variantId: $item->variantId(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'variantId' => $this->variantId,
            'quantity' => $this->quantity,
            'unitPriceAmount' => $this->unitPriceAmount,
            'unitPriceCurrency' => $this->unitPriceCurrency,
            'totalPriceAmount' => $this->totalPriceAmount,
            'totalPriceCurrency' => $this->totalPriceCurrency,
        ];
    }
}
