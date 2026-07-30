<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use DateTimeImmutable;

/**
 * Tracks stock for one Product within one Tenant. Deliberately a separate
 * entity from Product rather than columns on it (HANDOFF's own framing:
 * "Entity for DDD is better") — inventory has its own lifecycle
 * (on-hand vs. reserved) that has nothing to do with a Product's
 * name/price/description.
 *
 * available() is the only quantity a caller should ever check against —
 * quantityReserved exists so a unit already committed to someone's cart
 * can never be reserved again by a second Agent (the actual mechanism
 * that prevents overselling).
 *
 * Two-phase lifecycle (Phase 2, Order Management stage): reserve()/
 * release() are the *soft hold* a Cart places and lifts — they never
 * touch quantityOnHand, only quantityReserved, so available() reflects
 * the hold without any physical stock actually leaving. commit()/
 * restore() are the *hard* transition a placed/cancelled Order causes —
 * commit() is what actually decrements quantityOnHand (the sale really
 * happened) while simultaneously lifting the soft hold that covered it
 * (it is no longer "reserved in a cart", it is sold); restore() is
 * commit()'s exact inverse for a cancelled Order, putting stock directly
 * back on hand rather than re-reserving it into a phantom cart.
 */
final class Inventory
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $productId,
        private int $quantityOnHand,
        private int $quantityReserved,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function stock(int $tenantId, int $productId, int $quantityOnHand): self
    {
        return new self(
            id: null,
            tenantId: $tenantId,
            productId: $productId,
            quantityOnHand: $quantityOnHand,
            quantityReserved: 0,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function available(): int
    {
        return max(0, $this->quantityOnHand - $this->quantityReserved);
    }

    /**
     * The authoritative enforcement of "cannot reserve more than is
     * available" — kept here, not only in CheckInventoryAction's guard,
     * so the invariant holds even if a future caller reaches this entity
     * without going through that Action first.
     */
    public function reserve(Quantity $quantity): void
    {
        if ($quantity->value() > $this->available()) {
            throw new InsufficientInventoryException(
                "Only {$this->available()} unit(s) available for product [{$this->productId}], requested {$quantity->value()}."
            );
        }

        $this->quantityReserved += $quantity->value();
    }

    public function release(Quantity $quantity): void
    {
        $this->quantityReserved = max(0, $this->quantityReserved - $quantity->value());
    }

    /**
     * Converts a Cart's soft hold into an actual stock reduction when the
     * Order it belongs to is placed. Assumes the quantity being committed
     * was already reserved (PlaceOrderAction re-validates via
     * CheckInventoryAction before calling this) — does not itself
     * re-check availability, since "already reserved" already accounted
     * for it.
     */
    public function commit(Quantity $quantity): void
    {
        $this->quantityOnHand = max(0, $this->quantityOnHand - $quantity->value());
        $this->quantityReserved = max(0, $this->quantityReserved - $quantity->value());
    }

    /**
     * Reverses commit() when an Order is cancelled — puts stock directly
     * back on hand (not into quantityReserved; a cancelled Order's stock
     * is immediately available to anyone, not held for the cancelling
     * party).
     */
    public function restore(Quantity $quantity): void
    {
        $this->quantityOnHand += $quantity->value();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function quantityOnHand(): int
    {
        return $this->quantityOnHand;
    }

    public function quantityReserved(): int
    {
        return $this->quantityReserved;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
