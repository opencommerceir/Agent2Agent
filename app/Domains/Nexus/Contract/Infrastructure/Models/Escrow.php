<?php

namespace App\Domains\Nexus\Contract\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `nexus_escrows` table. Never used
 * directly outside the Infrastructure layer — the rest of the application
 * depends on App\Domains\Nexus\Contract\Domain\Entities\Escrow instead.
 */
class Escrow extends Model
{
    protected $table = 'nexus_escrows';

    protected $fillable = [
        'contract_id',
        'negotiation_id',
        'business_a_id',
        'business_b_id',
        'gross_amount',
        'currency',
        'platform_fee_percent',
        'platform_fee_amount',
        'net_amount',
        'status',
        'dispute_reason',
        'held_at',
        'released_at',
    ];

    protected $casts = [
        'gross_amount' => 'integer',
        'platform_fee_percent' => 'float',
        'platform_fee_amount' => 'integer',
        'net_amount' => 'integer',
        'held_at' => 'datetime',
        'released_at' => 'datetime',
    ];
}
