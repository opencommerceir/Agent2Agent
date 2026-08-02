<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\WarehouseTransferItem;

final class WarehouseTransferItemData
{
    public function __construct(
        public readonly int $productId,
        public readonly ?int $variantId,
        public readonly int $quantity,
    ) {
    }

    public static function fromEntity(WarehouseTransferItem $item): self
    {
        return new self($item->productId(), $item->variantId(), $item->quantity());
    }

    /**
     * @return array{productId: int, variantId: ?int, quantity: int}
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'variantId' => $this->variantId,
            'quantity' => $this->quantity,
        ];
    }
}
