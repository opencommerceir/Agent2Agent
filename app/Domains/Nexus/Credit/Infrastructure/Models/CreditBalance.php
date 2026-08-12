<?php

namespace App\Domains\Nexus\Credit\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `nexus_credit_balances` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Domains\Nexus\Credit\Domain\Entities\CreditBalance instead.
 */
class CreditBalance extends Model
{
    protected $table = 'nexus_credit_balances';

    protected $fillable = [
        'business_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'integer',
    ];
}
