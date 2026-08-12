<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `nexus_llm_usage_logs` table. Never
 * used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Domains\Nexus\Llm\Domain\Entities\LLMUsageLog instead. No
 * UPDATED_AT — the ledger is immutable (see the migration), same shape
 * App\Domains\Nexus\Credit\Infrastructure\Models\CreditTransaction uses.
 */
class LLMUsageLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'nexus_llm_usage_logs';

    protected $fillable = [
        'business_id',
        'agent_id',
        'feature',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'real_cost_usd',
        'charged_cost_usd',
        'margin_usd',
        'latency_ms',
        'from_fallback',
        'success',
        'error_message',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'real_cost_usd' => 'float',
        'charged_cost_usd' => 'float',
        'margin_usd' => 'float',
        'latency_ms' => 'integer',
        'from_fallback' => 'boolean',
        'success' => 'boolean',
    ];
}
