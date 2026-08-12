<?php

namespace App\Domains\Nexus\Credit\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `nexus_credit_purchase_sessions`
 * table. Never used directly outside the Infrastructure layer — the rest
 * of the application depends on
 * App\Domains\Nexus\Credit\Domain\Entities\CreditPurchaseSession instead.
 */
class CreditPurchaseSession extends Model
{
    protected $table = 'nexus_credit_purchase_sessions';

    protected $fillable = [
        'business_id',
        'gateway',
        'provider_reference',
        'package',
        'total_amount',
        'total_currency',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'completed_at' => 'datetime',
    ];
}
