<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `variant_attribute_values` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\VariantAttributeValue instead.
 */
class VariantAttributeValue extends Model
{
    public $timestamps = false;

    protected $table = 'variant_attribute_values';

    protected $fillable = [
        'attribute_id',
        'value',
        'display_order',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
