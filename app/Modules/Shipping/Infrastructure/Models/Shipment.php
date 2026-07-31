<?php

namespace App\Modules\Shipping\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent persistence model for the `shipments` table.
 * No `order()` belongsTo relation to Commerce's Eloquent Order model —
 * even at the Infrastructure layer, Shipping stays decoupled from
 * Commerce's Model classes (Dependency Inversion), the same choice
 * CRM's Tag Model makes for `customer_tag` (that Model's own docblock).
 */
class Shipment extends Model
{
    protected $table = 'shipments';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'shipping_method_id',
        'tracking_number',
        'status',
        'weight_grams',
        'shipping_cost_amount',
        'shipping_cost_currency',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }
}
