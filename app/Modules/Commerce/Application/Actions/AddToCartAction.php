<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\CartData;
use App\Modules\Commerce\Domain\Entities\Cart;
use App\Modules\Commerce\Domain\Events\InventoryReserved;
use App\Modules\Commerce\Domain\Events\ItemAddedToCart;
use App\Modules\Commerce\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * One Action = one business operation: add a Product to the owner's Cart
 * and reserve the matching Inventory, dispatching both domain events. No
 * pricing logic beyond snapshotting the Product's current price onto the
 * CartItem (Cart::addItem()) — discounts, tax, promotions are explicitly
 * out of scope here.
 *
 * CheckInventoryAction::authorize() gives an early, clearly-worded
 * failure before touching the database; the actual read-check-write of
 * the reservation happens inside its own DB::transaction() using
 * findByProductForUpdate() (a row lock), so two concurrent Agents
 * reserving against the same product serialize instead of both reading
 * the same available() snapshot and over-reserving past quantityOnHand.
 * Inventory::reserve() re-checks as the entity's own invariant inside
 * that locked transaction — the authoritative enforcement, not redundant
 * busywork (see that entity's docblock); the early authorize() call is
 * only a fast, clearly-worded rejection for the common (non-racing) case.
 */
final class AddToCartAction
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly InventoryRepositoryInterface $inventories,
        private readonly ProductRepositoryInterface $products,
        private readonly CheckInventoryAction $checkInventory,
    ) {
    }

    public function execute(
        int $tenantId,
        MemberType $ownerType,
        int $ownerId,
        int $productId,
        int $quantity,
    ): CartData {
        $requestedQuantity = new Quantity($quantity);

        $product = $this->products->findById($productId, $tenantId);

        if (! $product || ! $product->isActive()) {
            throw new ProductNotFoundException("Product [{$productId}] does not exist.");
        }

        $this->checkInventory->authorize($productId, $tenantId, $requestedQuantity);

        DB::transaction(function () use ($productId, $tenantId, $requestedQuantity): void {
            $inventory = $this->inventories->findByProductForUpdate($productId, $tenantId);

            if (! $inventory) {
                throw new InsufficientInventoryException("Product [{$productId}] has no inventory record.");
            }

            $inventory->reserve($requestedQuantity);
            $this->inventories->save($inventory);
        });

        Event::dispatch(new InventoryReserved($productId, $tenantId, $requestedQuantity));

        $cart = $this->carts->findActiveByOwner($tenantId, $ownerType, $ownerId)
            ?? Cart::open($tenantId, $ownerType, $ownerId);

        $cart->addItem($productId, $requestedQuantity, $product->price());
        $cart = $this->carts->save($cart);

        Event::dispatch(new ItemAddedToCart($cart, $productId, $requestedQuantity));

        return CartData::fromEntity($cart);
    }
}
