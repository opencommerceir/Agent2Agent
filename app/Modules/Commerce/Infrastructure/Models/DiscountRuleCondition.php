<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `discount_rule_conditions` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\DiscountRuleCondition instead.
 */
class DiscountRuleCondition extends Model
{
    public $timestamps = false;

    protected $table = 'discount_rule_conditions';

    protected $fillable = [
        'discount_rule_id',
        'condition_type',
        'condition_value',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'condition_value' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
