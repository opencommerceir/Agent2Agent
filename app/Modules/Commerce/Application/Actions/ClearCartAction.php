<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\CartData;
use App\Modules\Commerce\Domain\Entities\CartItem;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;

final class ClearCartAction
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    public function execute(int $tenantId, MemberType $ownerType, int $ownerId): CartData
    {
        $cart = $this->carts->findActiveByOwner($tenantId, $ownerType, $ownerId);

        if (! $cart) {
            throw new CartNotFoundException('No active cart found for this owner.');
        }

        $clearedItems = $cart->clear();

        foreach ($clearedItems as $item) {
            /** @var CartItem $item */
            $inventory = $this->inventories->findByProduct($item->productId(), $tenantId);

            if ($inventory) {
                $inventory->release($item->quantity());
                $this->inventories->save($inventory);
            }
        }

        $cart = $this->carts->save($cart);

        return CartData::fromEntity($cart);
    }
}
