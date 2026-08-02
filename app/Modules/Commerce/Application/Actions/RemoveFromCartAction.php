<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\CartData;
use App\Modules\Commerce\Domain\Events\ItemRemovedFromCart;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Support\Facades\Event;

/**
 * $variantId (Phase 5, Stage 1 — Product Variants, §7.21) is an optional
 * trailing param — null removes the parent Product's own line, exactly
 * as before this stage; a real value removes that specific
 * ProductVariant's line instead, releasing that variant's own Inventory
 * row rather than the parent Product's.
 */
final class RemoveFromCartAction
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    public function execute(int $tenantId, MemberType $ownerType, int $ownerId, int $productId, ?int $variantId = null): CartData
    {
        $cart = $this->carts->findActiveByOwner($tenantId, $ownerType, $ownerId);

        if (! $cart) {
            throw new CartNotFoundException('No active cart found for this owner.');
        }

        $removedItem = $cart->removeItem($productId, $variantId); // throws InvalidArgumentException if not in cart

        $inventory = $this->inventories->findByProduct($productId, $tenantId, $variantId);

        if ($inventory) {
            $inventory->release($removedItem->quantity());
            $this->inventories->save($inventory);
        }

        $cart = $this->carts->save($cart);

        Event::dispatch(new ItemRemovedFromCart($cart, $productId, $removedItem->quantity()));

        return CartData::fromEntity($cart);
    }
}
