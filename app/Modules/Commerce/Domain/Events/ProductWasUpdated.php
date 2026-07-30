<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Product;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a Product's changes have been persisted.
 */
final class ProductWasUpdated
{
    public function __construct(
        public readonly Product $product,
    ) {
    }
}
