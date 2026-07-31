<?php

namespace App\Modules\Finance\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `tax_rates` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Finance\Domain\Entities\TaxRate
 * instead.
 */
class TaxRate extends Model
{
    protected $table = 'tax_rates';

    protected $fillable = [
        'tenant_id',
        'region',
        'rate_percentage',
        'is_active',
    ];

    protected $casts = [
        'rate_percentage' => 'integer',
        'is_active' => 'boolean',
    ];
}
