<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\ProductVariant;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a ProductVariant has been persisted — mirrors
 * ProductWasCreated's own shape (carries the whole entity).
 */
final class VariantWasCreated
{
    public function __construct(
        public readonly ProductVariant $variant,
    ) {
    }
}
