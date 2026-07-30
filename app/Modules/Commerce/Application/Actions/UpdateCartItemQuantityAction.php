<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\CartData;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use InvalidArgumentException;

/**
 * Changing an item's quantity re-balances its Inventory reservation by
 * the delta rather than releasing-then-reserving the full amount: an
 * increase only needs to reserve the difference (and is checked against
 * availability the same way AddToCartAction is); a decrease only needs
 * to release the difference.
 */
final class UpdateCartItemQuantityAction
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly InventoryRepositoryInterface $inventories,
        private readonly CheckInventoryAction $checkInventory,
    ) {
    }

    public function execute(
        int $tenantId,
        MemberType $ownerType,
        int $ownerId,
        int $productId,
        int $newQuantity,
    ): CartData {
        $cart = $this->carts->findActiveByOwner($tenantId, $ownerType, $ownerId);

        if (! $cart) {
            throw new CartNotFoundException('No active cart found for this owner.');
        }

        $item = $cart->findItem($productId);

        if (! $item) {
            throw new InvalidArgumentException("Product [{$productId}] is not in this cart.");
        }

        $newQty = new Quantity($newQuantity);
        $delta = $newQty->value() - $item->quantity()->value();
        $inventory = $this->inventories->findByProduct($productId, $tenantId);

        if ($delta > 0) {
            $this->checkInventory->authorize($productId, $tenantId, new Quantity($delta));
            $inventory?->reserve(new Quantity($delta));
        } elseif ($delta < 0) {
            $inventory?->release(new Quantity(abs($delta)));
        }

        if ($inventory) {
            $this->inventories->save($inventory);
        }

        $item->changeQuantity($newQty);
        $cart = $this->carts->save($cart);

        return CartData::fromEntity($cart);
    }
}
