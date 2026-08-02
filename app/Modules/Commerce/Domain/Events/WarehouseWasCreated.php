<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Warehouse;

/**
 * Domain event: a fact that already happened. Dispatched after a
 * Warehouse has been persisted — mirrors VariantWasCreated's own shape
 * (carries the whole entity).
 */
final class WarehouseWasCreated
{
    public function __construct(
        public readonly Warehouse $warehouse,
    ) {
    }
}
