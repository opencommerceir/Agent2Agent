<?php

namespace App\Modules\Workflows\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `workflow_logs` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Workflows\Domain\Entities\WorkflowLog
 * instead. No `updated_at` — logs are immutable (WorkflowLog's own
 * docblock).
 */
class WorkflowLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'workflow_logs';

    protected $fillable = [
        'workflow_id',
        'tenant_id',
        'event_data',
        'actions_executed',
        'status',
    ];

    protected $casts = [
        'event_data' => 'array',
        'actions_executed' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
