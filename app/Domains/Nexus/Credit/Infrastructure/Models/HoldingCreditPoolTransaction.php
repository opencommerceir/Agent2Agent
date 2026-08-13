<?php

namespace App\Domains\Nexus\Credit\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class HoldingCreditPoolTransaction extends Model
{
    protected $table = 'nexus_holding_credit_pool_transactions';

    public $timestamps = false;

    protected $fillable = [
        'holding_id',
        'business_id',
        'type',
        'amount',
        'reason',
        'balance_after',
        'related_id',
        'created_at',
    ];
}
