<?php

namespace App\Modules\Reporting\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent persistence model for the `reports` table.
 * No `updated_at` — a Report is never edited after creation (Report
 * Entity's own docblock).
 */
class Report extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'reports';

    protected $fillable = [
        'tenant_id',
        'name',
        'report_type',
        'date_range_start',
        'date_range_end',
        'filters',
        'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'date_range_start' => 'date',
        'date_range_end' => 'date',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(ReportResult::class);
    }
}
