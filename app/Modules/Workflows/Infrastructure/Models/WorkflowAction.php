<?php

namespace App\Modules\Workflows\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `workflow_actions` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Workflows\Domain\Entities\WorkflowAction
 * instead. No `updated_at` — actions are immutable (Workflow's own
 * docblock).
 */
class WorkflowAction extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'workflow_actions';

    protected $fillable = [
        'workflow_id',
        'action_type',
        'parameters',
    ];

    protected $casts = [
        'parameters' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
