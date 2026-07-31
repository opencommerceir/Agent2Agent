<?php

namespace App\Modules\Workflows\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent persistence model for the `workflows` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Workflows\Domain\Entities\Workflow
 * instead.
 */
class Workflow extends Model
{
    protected $table = 'workflows';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'event_type',
        'status',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(WorkflowRule::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class);
    }
}
