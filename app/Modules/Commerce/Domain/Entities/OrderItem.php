<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;

/**
 * A frozen line item on a placed Order — no mutators at all (Immutable
 * Order Items rule): once an Order exists, only its status changes, never
 * what was ordered. unitPrice is copied from the CartItem's own snapshot
 * price, one snapshot layer further removed from the live Product price
 * than CartItem already was.
 */
final class OrderItem
{
    private function __construct(
        private readonly int $productId,
        private readonly Quantity $quantity,
        private readonly Money $unitPrice,
    ) {
    }

    public static function create(int $productId, Quantity $quantity, Money $unitPrice): self
    {
        return new self($productId, $quantity, $unitPrice);
    }

    public static function fromCartItem(CartItem $cartItem): self
    {
        return new self($cartItem->productId(), $cartItem->quantity(), $cartItem->unitPrice());
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function totalAmount(): int
    {
        return $this->quantity->value() * $this->unitPrice->amount();
    }
}
