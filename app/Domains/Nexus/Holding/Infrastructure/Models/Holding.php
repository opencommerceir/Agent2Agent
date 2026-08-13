<?php

namespace App\Domains\Nexus\Holding\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Holding extends Model
{
    protected $table = 'nexus_holdings';

    protected $fillable = [
        'parent_business_id',
        'name_fa',
        'name_en',
        'status',
    ];
}
