<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `subscription_plans` table. Never
 * used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\SubscriptionPlan instead.
 */
class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plans';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'billing_cycle',
        'price_amount',
        'price_currency',
        'trial_days',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
