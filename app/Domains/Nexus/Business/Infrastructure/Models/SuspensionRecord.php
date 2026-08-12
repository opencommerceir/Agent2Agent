<?php

namespace App\Domains\Nexus\Business\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class SuspensionRecord extends Model
{
    const UPDATED_AT = null;

    protected $table = 'nexus_suspension_records';

    protected $fillable = [
        'business_id',
        'action',
        'reason',
        'triggered_by',
    ];
}
