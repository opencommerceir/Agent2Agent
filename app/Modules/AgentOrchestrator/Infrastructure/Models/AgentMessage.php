<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $from_agent_type
 * @property string $to_agent_type
 * @property string $message_type
 * @property array $content
 * @property string $status
 * @property ?int $parent_execution_id
 * @property ?\Illuminate\Support\Carbon $processed_at
 * @property \Illuminate\Support\Carbon $created_at
 */
class AgentMessage extends Model
{
    protected $table = 'agent_messages';

    protected $fillable = [
        'tenant_id', 'from_agent_type', 'to_agent_type', 'message_type',
        'content', 'status', 'parent_execution_id', 'processed_at',
    ];

    protected $casts = [
        'content' => 'array',
        'processed_at' => 'datetime',
    ];
}
