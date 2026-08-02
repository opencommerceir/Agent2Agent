<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent persistence model for the `variant_attributes` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\VariantAttribute instead.
 */
class VariantAttribute extends Model
{
    public $timestamps = false;

    protected $table = 'variant_attributes';

    protected $fillable = [
        'tenant_id',
        'name',
        'display_order',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class, 'attribute_id');
    }
}
