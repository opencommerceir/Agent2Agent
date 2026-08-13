<?php

namespace App\Domains\Nexus\PrivateMarketplace\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class PrivateMarketplaceListing extends Model
{
    protected $table = 'nexus_private_marketplace_listings';

    public $timestamps = false;

    protected $fillable = [
        'private_marketplace_id',
        'listing_business_id',
        'catalog_item_type',
        'catalog_item_id',
        'special_price_amount',
        'special_price_currency',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
