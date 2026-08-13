<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `nexus_api_keys` table. Never used
 * directly outside the Infrastructure layer — the rest of the application
 * depends on App\Domains\Nexus\Developer\Domain\Entities\ApiKey instead.
 */
class ApiKey extends Model
{
    protected $table = 'nexus_api_keys';

    protected $fillable = [
        'business_id',
        'key_hash',
        'key_prefix',
        'label',
        'scopes',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}
