<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property ?int $parent_execution_id
 * @property string $from_agent_type
 * @property string $to_agent_type
 * @property string $task
 * @property int $priority
 * @property int $timeout_seconds
 * @property string $status
 * @property ?array $result
 * @property ?\Illuminate\Support\Carbon $completed_at
 * @property \Illuminate\Support\Carbon $created_at
 */
class DelegationRequest extends Model
{
    protected $table = 'delegation_requests';

    protected $fillable = [
        'tenant_id', 'parent_execution_id', 'from_agent_type', 'to_agent_type',
        'task', 'priority', 'timeout_seconds', 'status', 'result', 'completed_at',
    ];

    protected $casts = [
        'result' => 'array',
        'completed_at' => 'datetime',
    ];
}
