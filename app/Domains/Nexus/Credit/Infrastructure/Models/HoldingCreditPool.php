<?php

namespace App\Domains\Nexus\Credit\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class HoldingCreditPool extends Model
{
    protected $table = 'nexus_holding_credit_pools';

    protected $fillable = [
        'holding_id',
        'balance',
    ];
}
