<?php

namespace App\Modules\Loyalty\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `point_transactions` table.
 * No `updated_at` — a ledger entry is immutable (PointTransaction
 * Entity's own docblock).
 */
class PointTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'point_transactions';

    protected $fillable = [
        'tenant_id',
        'loyalty_account_id',
        'points',
        'transaction_type',
        'description',
        'reference_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function loyaltyAccount(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }
}
