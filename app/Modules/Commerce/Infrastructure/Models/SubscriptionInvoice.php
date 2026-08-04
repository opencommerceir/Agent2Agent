<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `subscription_invoices` table. Never
 * used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\SubscriptionInvoice instead.
 */
class SubscriptionInvoice extends Model
{
    protected $table = 'subscription_invoices';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'order_id',
        'amount',
        'currency',
        'status',
        'due_date',
        'paid_at',
        'failed_at',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
