<?php

namespace App\Core\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `agents` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Core\Domain\Entities\Agent instead.
 */
class Agent extends Model
{
    protected $table = 'agents';

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'name',
        'type',
        'status',
    ];

    public function tokens()
    {
        return $this->hasMany(AgentToken::class);
    }
}
