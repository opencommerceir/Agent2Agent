<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `warehouse_transfer_items` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\WarehouseTransferItem instead.
 * No `updated_at` — mirrors the entity's own "frozen at creation" shape.
 */
class WarehouseTransferItem extends Model
{
    public $timestamps = false;

    protected $table = 'warehouse_transfer_items';

    protected $fillable = [
        'transfer_id',
        'product_id',
        'variant_id',
        'quantity',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
