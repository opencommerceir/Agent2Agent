<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\Cart;

/**
 * totalAmount sums each item's subtotal, and currency is taken from the
 * first item — a real multi-currency cart is out of scope for this
 * phase (every Product a tenant sells is assumed to share one currency,
 * same assumption CreateProductAction/UpdateProductAction don't
 * contradict since neither enforces a tenant-wide currency today).
 */
final class CartData
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $ownerType,
        public readonly int $ownerId,
        public readonly string $status,
        public readonly array $items,
        public readonly int $totalAmount,
        public readonly ?string $currency,
    ) {
    }

    public static function fromEntity(Cart $cart): self
    {
        $items = $cart->items();
        $itemData = array_map(fn ($item) => CartItemData::fromEntity($item), $items);

        return new self(
            id: $cart->id(),
            tenantId: $cart->tenantId(),
            ownerType: $cart->ownerType()->value,
            ownerId: $cart->ownerId(),
            status: $cart->status()->value,
            items: array_map(fn (CartItemData $data) => $data->toArray(), $itemData),
            totalAmount: array_sum(array_map(fn (CartItemData $data) => $data->subtotalAmount, $itemData)),
            currency: $itemData[0]->priceCurrency ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'ownerType' => $this->ownerType,
            'ownerId' => $this->ownerId,
            'status' => $this->status,
            'items' => $this->items,
            'totalAmount' => $this->totalAmount,
            'currency' => $this->currency,
        ];
    }
}
