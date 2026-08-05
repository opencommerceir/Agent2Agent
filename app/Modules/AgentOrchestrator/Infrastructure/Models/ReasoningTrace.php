<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $execution_id
 * @property string $agent_type
 * @property string $goal_text
 * @property string $reasoning_type
 * @property array $thoughts
 * @property ?array $alternatives
 * @property float $confidence_score
 * @property string $decision
 * @property string $explanation
 * @property \Illuminate\Support\Carbon $created_at
 */
class ReasoningTrace extends Model
{
    public $timestamps = false;

    protected $table = 'reasoning_traces';

    protected $fillable = [
        'tenant_id', 'execution_id', 'agent_type', 'goal_text', 'reasoning_type',
        'thoughts', 'alternatives', 'confidence_score', 'decision', 'explanation', 'created_at',
    ];

    protected $casts = [
        'thoughts' => 'array',
        'alternatives' => 'array',
        'confidence_score' => 'float',
        'created_at' => 'datetime',
    ];
}
