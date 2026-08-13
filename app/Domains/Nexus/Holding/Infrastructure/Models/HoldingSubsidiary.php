<?php

namespace App\Domains\Nexus\Holding\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class HoldingSubsidiary extends Model
{
    protected $table = 'nexus_holding_subsidiaries';

    public $timestamps = false;

    protected $fillable = [
        'holding_id',
        'business_id',
        'status',
        'invited_at',
        'responded_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'responded_at' => 'datetime',
    ];
}
