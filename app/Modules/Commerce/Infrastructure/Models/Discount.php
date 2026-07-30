<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `discounts` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Commerce\Domain\Entities\Discount
 * instead. Rows are created once and never updated (Discount entity's
 * own docblock).
 */
class Discount extends Model
{
    protected $table = 'discounts';

    protected $fillable = [
        'order_id',
        'coupon_id',
        'discount_type',
        'discount_amount',
        'discount_currency',
        'description',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
