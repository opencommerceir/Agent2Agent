<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for `nexus_integration_connections`.
 * `auth_token` uses Eloquent's built-in `encrypted` cast — decryptable
 * (unlike ApiKey's one-way hash) since SyncCatalogToIntegrationAction
 * needs the plaintext to authenticate against the Business's own target
 * system.
 */
class IntegrationConnection extends Model
{
    protected $table = 'nexus_integration_connections';

    protected $fillable = [
        'business_id',
        'category',
        'name',
        'target_url',
        'auth_token',
        'field_mapping',
        'revoked_at',
    ];

    protected $casts = [
        'field_mapping' => 'array',
        'auth_token' => 'encrypted',
        'revoked_at' => 'datetime',
    ];
}
