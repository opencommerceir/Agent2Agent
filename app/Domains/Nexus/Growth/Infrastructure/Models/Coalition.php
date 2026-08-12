<?php

namespace App\Domains\Nexus\Growth\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Coalition extends Model
{
    protected $table = 'nexus_coalitions';

    protected $fillable = [
        'organizer_business_id',
        'target_business_id',
        'catalog_item_type',
        'catalog_item_id',
        'unit_price_amount',
        'unit_price_currency',
        'min_participants',
        'discount_percent',
        'status',
        'negotiation_id',
    ];

    protected $casts = [
        'discount_percent' => 'float',
    ];
}
