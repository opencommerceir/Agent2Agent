<?php

namespace App\Domains\Nexus\Audit\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for `nexus_audit_log_entries`. Never used
 * directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Domains\Nexus\Audit\Domain\Entities\AuditLogEntry instead. No
 * UPDATED_AT — the chain is immutable (see the migration).
 */
class AuditLogEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'nexus_audit_log_entries';

    protected $fillable = [
        'sequence',
        'prev_hash',
        'entry_hash',
        'capability_name',
        'business_id',
        'core_agent_id',
        'status',
        'input_summary',
        'execution_time_ms',
        'created_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'input_summary' => 'array',
        'execution_time_ms' => 'integer',
    ];
}
