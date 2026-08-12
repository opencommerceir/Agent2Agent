<?php

namespace App\Domains\Nexus\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'nexus_services';

    protected $fillable = [
        'business_id',
        'name_fa',
        'name_en',
        'price_amount',
        'price_currency',
        'duration_minutes',
        'attributes',
        'verification_status',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];
}
