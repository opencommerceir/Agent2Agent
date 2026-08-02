<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\ProductVariant;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a ProductVariant's price/image/active-status has been
 * updated and persisted.
 */
final class VariantWasUpdated
{
    public function __construct(
        public readonly ProductVariant $variant,
    ) {
    }
}
