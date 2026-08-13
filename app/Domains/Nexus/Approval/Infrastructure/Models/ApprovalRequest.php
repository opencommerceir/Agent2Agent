<?php

namespace App\Domains\Nexus\Approval\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $table = 'nexus_approval_requests';

    protected $fillable = [
        'negotiation_id',
        'business_id',
        'required_levels',
        'current_level_index',
        'status',
    ];

    protected $casts = [
        'required_levels' => 'array',
    ];
}
