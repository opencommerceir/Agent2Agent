<?php

namespace App\Domains\Nexus\Approval\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalPolicy extends Model
{
    protected $table = 'nexus_approval_policies';

    protected $fillable = [
        'business_id',
        'levels',
    ];

    protected $casts = [
        'levels' => 'array',
    ];
}
