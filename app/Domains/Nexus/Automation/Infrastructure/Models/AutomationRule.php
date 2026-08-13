<?php

namespace App\Domains\Nexus\Automation\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRule extends Model
{
    protected $table = 'nexus_automation_rules';

    protected $fillable = [
        'business_id',
        'type',
        'config',
        'status',
        'last_triggered_at',
    ];

    protected $casts = [
        'config' => 'array',
        'last_triggered_at' => 'datetime',
    ];
}
