<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `order_items` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Commerce\Domain\Entities\OrderItem
 * instead. Rows are created once alongside their Order and never
 * updated (Immutable Order Items rule).
 */
class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price_amount',
        'unit_price_currency',
        'total_price_amount',
        'total_price_currency',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
