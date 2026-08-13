<?php

namespace App\Domains\Nexus\Automation\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRunLog extends Model
{
    protected $table = 'nexus_automation_run_logs';

    public $timestamps = false;

    protected $fillable = [
        'automation_rule_id',
        'business_id',
        'outcome',
        'detail',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
