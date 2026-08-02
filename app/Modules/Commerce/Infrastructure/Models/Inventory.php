<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `inventories` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Commerce\Domain\Entities\Inventory
 * instead.
 */
class Inventory extends Model
{
    protected $table = 'inventories';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'variant_id',
        'quantity_on_hand',
        'quantity_reserved',
    ];
}
