<?php

namespace App\Modules\Analytics\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class KPIValue extends Model
{
    protected $table = 'kpi_values';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'kpi_id', 'value_amount', 'value_currency',
        'time_period', 'period_start', 'period_end', 'calculated_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'calculated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
