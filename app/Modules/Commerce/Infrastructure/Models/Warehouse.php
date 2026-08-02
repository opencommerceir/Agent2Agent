<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `warehouses` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Commerce\Domain\Entities\Warehouse
 * instead.
 */
class Warehouse extends Model
{
    protected $table = 'warehouses';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'address',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
