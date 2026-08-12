<?php

namespace App\Domains\Nexus\Contract\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeCase extends Model
{
    protected $table = 'nexus_dispute_cases';

    protected $fillable = [
        'escrow_id',
        'negotiation_id',
        'business_a_id',
        'business_b_id',
        'opened_by_business_id',
        'reason',
        'evidence',
        'status',
        'resolution',
        'resolved_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'resolved_at' => 'datetime',
    ];
}
