<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent persistence model for the `warehouse_transfers` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\WarehouseTransfer instead.
 */
class WarehouseTransfer extends Model
{
    protected $table = 'warehouse_transfers';

    protected $fillable = [
        'tenant_id',
        'source_warehouse_id',
        'destination_warehouse_id',
        'status',
        'requested_by',
        'approved_by',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseTransferItem::class, 'transfer_id');
    }
}
