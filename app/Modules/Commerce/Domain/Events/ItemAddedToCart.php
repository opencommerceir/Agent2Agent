<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Cart;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a Product has been added (or its quantity increased)
 * in a Cart and inventory has been reserved for it.
 */
final class ItemAddedToCart
{
    public function __construct(
        public readonly Cart $cart,
        public readonly int $productId,
        public readonly Quantity $quantity,
    ) {
    }
}
