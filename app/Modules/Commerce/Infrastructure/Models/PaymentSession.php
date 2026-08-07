<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `payment_sessions` table (§7.37).
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\PaymentSession instead.
 */
class PaymentSession extends Model
{
    protected $table = 'payment_sessions';

    protected $fillable = [
        'tenant_id',
        'cart_id',
        'agent_id',
        'gateway',
        'provider_reference',
        'total_amount',
        'tax_amount',
        'discount_amount',
        'currency',
        'status',
        'coupon_code',
        'customer_id',
        'notes',
        'region',
        'order_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
