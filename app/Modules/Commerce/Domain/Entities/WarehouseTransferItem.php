<?php

namespace App\Modules\Commerce\Domain\Entities;

use InvalidArgumentException;

/**
 * One line of a WarehouseTransfer — how many units of one Product (or one
 * specific ProductVariant) move from the source to the destination
 * Warehouse. No `id`/`transferId` property on the Domain entity, the same
 * HANDOFF gotcha #10 shape OrderItem/InvoiceItem/WorkflowRule already
 * have — nothing ever looks one up individually, only ever as part of its
 * parent WarehouseTransfer. `warehouse_transfer_items` itself still has a
 * real `id` primary key, like every table does.
 */
final class WarehouseTransferItem
{
    public function __construct(
        private readonly int $productId,
        private readonly ?int $variantId,
        private readonly int $quantity,
    ) {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("WarehouseTransferItem quantity must be positive, got [{$quantity}].");
        }
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function variantId(): ?int
    {
        return $this->variantId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }
}
