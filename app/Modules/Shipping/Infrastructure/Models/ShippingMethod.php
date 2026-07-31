<?php

namespace App\Modules\Shipping\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    protected $table = 'shipping_methods';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'base_rate_amount',
        'base_rate_currency',
        'rate_per_kg_amount',
        'rate_per_kg_currency',
        'estimated_days_min',
        'estimated_days_max',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
