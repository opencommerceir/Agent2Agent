<?php

namespace App\Domains\Nexus\Agent\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `nexus_agents` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Domains\Nexus\Agent\Domain\Entities\Agent
 * instead.
 */
class Agent extends Model
{
    protected $table = 'nexus_agents';

    protected $fillable = [
        'business_id',
        'core_agent_id',
        'name_fa',
        'name_en',
        'personality',
        'tone',
        'authority_limits',
        'strategies',
    ];

    protected $casts = [
        'authority_limits' => 'array',
        'strategies' => 'array',
    ];
}
