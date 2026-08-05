<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $agent_id
 * @property string $agent_type
 * @property string $goal_text
 * @property string $status
 * @property string $summary
 * @property int $execution_time_ms
 */
class Execution extends Model
{
    protected $table = 'agent_executions';

    protected $fillable = [
        'tenant_id', 'agent_id', 'agent_type', 'goal_text', 'status', 'summary', 'execution_time_ms',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(ExecutionStep::class, 'agent_execution_id')->orderBy('sequence');
    }
}
