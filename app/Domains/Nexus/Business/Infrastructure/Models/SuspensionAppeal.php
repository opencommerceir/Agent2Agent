<?php

namespace App\Domains\Nexus\Business\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class SuspensionAppeal extends Model
{
    protected $table = 'nexus_suspension_appeals';

    protected $fillable = [
        'business_id',
        'message',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];
}
