<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class AgentStrategyTemplate extends Model
{
    protected $table = 'nexus_agent_strategy_templates';

    protected $fillable = [
        'publisher_business_id',
        'name_fa',
        'name_en',
        'description_fa',
        'description_en',
        'personality',
        'tone',
        'strategies',
        'price_credits',
        'install_count',
        'revoked_at',
    ];

    protected $casts = [
        'strategies' => 'array',
        'price_credits' => 'integer',
        'install_count' => 'integer',
        'revoked_at' => 'datetime',
    ];
}
