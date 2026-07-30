<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\CartData;
use App\Modules\Commerce\Domain\Entities\Cart;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;

/**
 * An owner with no cart yet is a normal, non-error state (a shopper who
 * hasn't added anything) — this returns a transient, never-persisted
 * empty Cart rather than throwing CartNotFoundException, which is
 * reserved for mutating Actions (Remove/UpdateQuantity/Clear) acting on
 * a cart that was expected to already exist.
 */
final class GetCartAction
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
    ) {
    }

    public function execute(int $tenantId, MemberType $ownerType, int $ownerId): CartData
    {
        $cart = $this->carts->findActiveByOwner($tenantId, $ownerType, $ownerId)
            ?? Cart::open($tenantId, $ownerType, $ownerId);

        return CartData::fromEntity($cart);
    }
}
