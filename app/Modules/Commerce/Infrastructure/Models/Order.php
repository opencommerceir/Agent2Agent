<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent persistence model for the `orders` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Commerce\Domain\Entities\Order
 * instead.
 */
class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'tenant_id',
        'agent_id',
        'customer_id',
        'order_number',
        'status',
        'subtotal_amount',
        'subtotal_currency',
        'total_amount',
        'total_currency',
        'notes',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
