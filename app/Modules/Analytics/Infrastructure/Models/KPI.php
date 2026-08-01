<?php

namespace App\Modules\Analytics\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class KPI extends Model
{
    protected $table = 'kpis';

    protected $fillable = ['tenant_id', 'type', 'name', 'description', 'calculation_formula', 'is_active'];

    protected function casts(): array
    {
        return [
            'calculation_formula' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
