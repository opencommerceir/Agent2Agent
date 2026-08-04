<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `applied_discounts` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\AppliedDiscount instead.
 */
class AppliedDiscount extends Model
{
    public $timestamps = false;

    protected $table = 'applied_discounts';

    protected $fillable = [
        'tenant_id',
        'cart_id',
        'discount_rule_id',
        'coupon_id',
        'discount_type',
        'discount_amount',
        'discount_currency',
        'applied_to',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'applied_to' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
