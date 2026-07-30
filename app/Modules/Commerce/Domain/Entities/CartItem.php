<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;

/**
 * One line in a Cart. unitPrice is a snapshot of the Product's price at
 * the moment it was added — not a live reference — so a later price
 * change on the Product never silently changes what's already sitting in
 * someone's cart. No identity of its own beyond productId (unique within
 * a Cart, enforced by Cart::addItem()); EloquentCartRepository persists
 * the owning Cart's items as a whole rather than tracking per-item ids.
 */
final class CartItem
{
    private function __construct(
        private readonly int $productId,
        private Quantity $quantity,
        private readonly Money $unitPrice,
    ) {
    }

    public static function create(int $productId, Quantity $quantity, Money $unitPrice): self
    {
        return new self($productId, $quantity, $unitPrice);
    }

    public function increaseQuantity(Quantity $by): void
    {
        $this->quantity = $this->quantity->add($by);
    }

    public function changeQuantity(Quantity $quantity): void
    {
        $this->quantity = $quantity;
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

    public function subtotalAmount(): int
    {
        return $this->quantity->value() * $this->unitPrice->amount();
    }
}
