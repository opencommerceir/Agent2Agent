<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `payments` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Commerce\Domain\Entities\Payment
 * instead.
 */
class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'transaction_id',
        'gateway_response',
    ];

    protected $casts = [
        'gateway_response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
