<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $goal_pattern
 * @property string $agent_type
 * @property array $successful_capabilities
 * @property array $failed_capabilities
 * @property int $usage_count
 * @property float $success_rate
 * @property \Illuminate\Support\Carbon $last_used_at
 */
class ExecutionPattern extends Model
{
    protected $table = 'execution_patterns';

    protected $fillable = [
        'tenant_id', 'goal_pattern', 'agent_type', 'successful_capabilities',
        'failed_capabilities', 'usage_count', 'success_rate', 'last_used_at',
    ];

    protected $casts = [
        'successful_capabilities' => 'array',
        'failed_capabilities' => 'array',
        'success_rate' => 'float',
        'last_used_at' => 'datetime',
    ];
}
