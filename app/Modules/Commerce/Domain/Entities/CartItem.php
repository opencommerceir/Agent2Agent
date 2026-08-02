<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;

/**
 * One line in a Cart. unitPrice is a snapshot of the Product's (or
 * ProductVariant's) price at the moment it was added — not a live
 * reference — so a later price change never silently changes what's
 * already sitting in someone's cart. Identity within a Cart is
 * (productId, variantId) together, enforced by Cart::addItem()/findItem()
 * — variantId is an optional trailing field (Phase 5, Stage 1, §7.21,
 * HANDOFF §3 pattern #6): null means this line is the parent Product
 * itself, exactly as every CartItem was before this stage; a real value
 * means this line is one specific ProductVariant. Two different variants
 * of the same Product are two separate lines, never merged into one.
 * EloquentCartRepository persists the owning Cart's items as a whole
 * rather than tracking per-item ids.
 */
final class CartItem
{
    private function __construct(
        private readonly int $productId,
        private Quantity $quantity,
        private readonly Money $unitPrice,
        private readonly ?int $variantId = null,
    ) {
    }

    public static function create(int $productId, Quantity $quantity, Money $unitPrice, ?int $variantId = null): self
    {
        return new self($productId, $quantity, $unitPrice, $variantId);
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

    public function subtotalAmount(): int
    {
        return $this->quantity->value() * $this->unitPrice->amount();
    }
}
