<?php

namespace App\Modules\Loyalty\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent persistence model for the `loyalty_accounts` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Loyalty\Domain\Entities\LoyaltyAccount instead.
 *
 * No `customer()` belongsTo relation to Commerce's Eloquent Customer
 * model — same Infrastructure-layer decoupling CRM's own Tag Model
 * establishes for `customer_tag` (see that Model's own docblock):
 * EloquentLoyaltyAccountRepository queries `customer_id` as a plain
 * column, never an Eloquent relation into another module's table.
 */
class LoyaltyAccount extends Model
{
    protected $table = 'loyalty_accounts';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'total_points_earned',
        'total_points_redeemed',
        'current_balance',
    ];

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'loyalty_account_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class, 'loyalty_account_id');
    }
}
