<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $agent_execution_id
 * @property int $sequence
 * @property string $capability
 * @property array $input
 * @property string $priority
 * @property string $status
 * @property ?array $output
 * @property ?string $error_message
 */
class ExecutionStep extends Model
{
    protected $table = 'agent_execution_steps';

    protected $fillable = [
        'agent_execution_id', 'sequence', 'capability', 'input', 'priority', 'status', 'output', 'error_message',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class, 'agent_execution_id');
    }
}
