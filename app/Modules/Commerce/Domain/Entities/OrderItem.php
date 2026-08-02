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
 *
 * variantId (Phase 5, Stage 1 — Product Variants, §7.21) is an optional
 * trailing field, copied straight through from the CartItem being frozen
 * — null for a plain Product line, exactly as every OrderItem was before
 * this stage.
 */
final class OrderItem
{
    private function __construct(
        private readonly int $productId,
        private readonly Quantity $quantity,
        private readonly Money $unitPrice,
        private readonly ?int $variantId = null,
    ) {
    }

    public static function create(int $productId, Quantity $quantity, Money $unitPrice, ?int $variantId = null): self
    {
        return new self($productId, $quantity, $unitPrice, $variantId);
    }

    public static function fromCartItem(CartItem $cartItem): self
    {
        return new self($cartItem->productId(), $cartItem->quantity(), $cartItem->unitPrice(), $cartItem->variantId());
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function variantId(): ?int
    {
        return $this->variantId;
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
