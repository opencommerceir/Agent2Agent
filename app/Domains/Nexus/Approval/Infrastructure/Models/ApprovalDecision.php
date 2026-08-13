<?php

namespace App\Domains\Nexus\Approval\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalDecision extends Model
{
    protected $table = 'nexus_approval_decisions';

    public $timestamps = false;

    protected $fillable = [
        'approval_request_id',
        'level_index',
        'role_required',
        'decided_by_owner_id',
        'decision',
        'decided_at',
    ];
}
