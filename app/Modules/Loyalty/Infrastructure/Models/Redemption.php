<?php

namespace App\Modules\Loyalty\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `redemptions` table.
 * No `updated_at` — a Redemption is written once (Redemption Entity's
 * own docblock).
 */
class Redemption extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'redemptions';

    protected $fillable = [
        'tenant_id',
        'loyalty_account_id',
        'reward_id',
        'points_used',
        'status',
    ];

    public function loyaltyAccount(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class, 'reward_id');
    }
}
