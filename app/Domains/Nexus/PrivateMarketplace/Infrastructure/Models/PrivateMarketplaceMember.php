<?php

namespace App\Domains\Nexus\PrivateMarketplace\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class PrivateMarketplaceMember extends Model
{
    protected $table = 'nexus_private_marketplace_members';

    public $timestamps = false;

    protected $fillable = [
        'private_marketplace_id',
        'business_id',
        'status',
        'invited_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
    ];
}
