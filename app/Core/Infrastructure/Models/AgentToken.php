<?php

namespace App\Core\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `agent_tokens` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Core\Domain\Entities\AgentToken instead.
 */
class AgentToken extends Model
{
    protected $table = 'agent_tokens';

    protected $fillable = [
        'agent_id',
        'token_hash',
        'label',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
