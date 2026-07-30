<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\CartItem;

final class CartItemData
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly int $priceAmount,
        public readonly string $priceCurrency,
        public readonly int $subtotalAmount,
    ) {
    }

    public static function fromEntity(CartItem $item): self
    {
        return new self(
            productId: $item->productId(),
            quantity: $item->quantity()->value(),
            priceAmount: $item->unitPrice()->amount(),
            priceCurrency: $item->unitPrice()->currency(),
            subtotalAmount: $item->subtotalAmount(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'quantity' => $this->quantity,
            'priceAmount' => $this->priceAmount,
            'priceCurrency' => $this->priceCurrency,
            'subtotalAmount' => $this->subtotalAmount,
        ];
    }
}
