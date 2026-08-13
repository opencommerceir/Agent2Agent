<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for `nexus_webhook_deliveries`. No
 * `updated_at` — immutable ledger, same shape CreditTransaction/
 * LLMUsageLog already established.
 */
class WebhookDeliveryLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'nexus_webhook_deliveries';

    protected $fillable = [
        'business_id',
        'subscription_id',
        'event',
        'url',
        'succeeded',
        'http_status',
        'error_message',
    ];

    protected $casts = [
        'succeeded' => 'boolean',
        'created_at' => 'datetime',
    ];
}
