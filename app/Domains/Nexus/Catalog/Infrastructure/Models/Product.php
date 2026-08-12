<?php

namespace App\Domains\Nexus\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'nexus_products';

    protected $fillable = [
        'business_id',
        'name_fa',
        'name_en',
        'price_amount',
        'price_currency',
        'stock_quantity',
        'attributes',
        'verification_status',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];
}
