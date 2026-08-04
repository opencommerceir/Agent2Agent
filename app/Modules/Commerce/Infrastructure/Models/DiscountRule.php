<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent persistence model for the `discount_rules` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\DiscountRule instead.
 */
class DiscountRule extends Model
{
    protected $table = 'discount_rules';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'priority',
        'stackability',
        'starts_at',
        'expires_at',
        'is_active',
        'max_uses',
        'used_count',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(DiscountRuleCondition::class);
    }
}
