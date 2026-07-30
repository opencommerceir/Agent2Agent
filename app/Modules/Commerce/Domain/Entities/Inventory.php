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
