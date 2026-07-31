<?php

namespace App\Modules\Loyalty\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    protected $table = 'rewards';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'reward_type',
        'points_required',
        'discount_amount',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
