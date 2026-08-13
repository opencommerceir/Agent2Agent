<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for `nexus_webhook_subscriptions`. `secret`
 * uses Eloquent's built-in `encrypted` cast — decryptable (unlike ApiKey's
 * one-way hash) since DispatchWebhookEventAction needs the plaintext to
 * sign outgoing payloads.
 */
class WebhookSubscription extends Model
{
    protected $table = 'nexus_webhook_subscriptions';

    protected $fillable = [
        'business_id',
        'url',
        'secret',
        'events',
        'revoked_at',
    ];

    protected $casts = [
        'events' => 'array',
        'secret' => 'encrypted',
        'revoked_at' => 'datetime',
    ];
}
