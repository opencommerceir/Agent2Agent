<?php

namespace App\Domains\Nexus\Credit\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `nexus_credit_transactions` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Domains\Nexus\Credit\Domain\Entities\CreditTransaction instead.
 * No UPDATED_AT — the ledger is immutable (see the migration).
 */
class CreditTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'nexus_credit_transactions';

    protected $fillable = [
        'business_id',
        'type',
        'amount',
        'reason',
        'balance_after',
        'related_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
    ];
}
