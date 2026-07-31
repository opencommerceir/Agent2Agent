<?php

namespace App\Modules\Workflows\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `workflow_rules` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Workflows\Domain\Entities\WorkflowRule
 * instead. No `updated_at` — rules are immutable (Workflow's own
 * docblock).
 */
class WorkflowRule extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'workflow_rules';

    protected $fillable = [
        'workflow_id',
        'condition_type',
        'field',
        'threshold_value',
    ];

    protected $casts = [
        'threshold_value' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
