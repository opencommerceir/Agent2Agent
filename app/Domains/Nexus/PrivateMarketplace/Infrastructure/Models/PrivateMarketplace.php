<?php

namespace App\Domains\Nexus\PrivateMarketplace\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class PrivateMarketplace extends Model
{
    protected $table = 'nexus_private_marketplaces';

    protected $fillable = [
        'owner_business_id',
        'name_fa',
        'name_en',
        'branding_primary_color',
        'status',
    ];
}
