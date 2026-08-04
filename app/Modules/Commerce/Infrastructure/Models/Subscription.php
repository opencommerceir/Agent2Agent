<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `subscriptions` table. Never used
 * directly outside the Infrastructure layer — the rest of the application
 * depends on App\Modules\Commerce\Domain\Entities\Subscription instead.
 */
class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'subscription_plan_id',
        'status',
        'current_period_start',
        'current_period_end',
        'trial_start',
        'trial_end',
        'paused_at',
        'cancelled_at',
        'cancel_at_period_end',
        'payment_method_id',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_start' => 'datetime',
            'trial_end' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }
}
