<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `coupons` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Commerce\Domain\Entities\Coupon
 * instead.
 */
class Coupon extends Model
{
    protected $table = 'coupons';

    protected $fillable = [
        'tenant_id',
        'code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'expires_at',
        'is_active',
        'discount_rule_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
