<?php

namespace App\Modules\Reporting\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `report_results` table.
 * No Eloquent-managed timestamps at all — the table has no `created_at`
 * column (`generated_at` already serves that purpose, set explicitly by
 * ReportResult::generate() rather than left to the framework) and no
 * `updated_at` either, since a computed result is immutable (ReportResult
 * Entity's own docblock).
 */
class ReportResult extends Model
{
    public $timestamps = false;

    protected $table = 'report_results';

    protected $fillable = [
        'report_id',
        'tenant_id',
        'result_data',
        'generated_at',
        'expires_at',
    ];

    protected $casts = [
        'result_data' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
