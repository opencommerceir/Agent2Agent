<?php

namespace App\Modules\Finance\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `invoice_items` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Finance\Domain\Entities\InvoiceItem
 * instead.
 */
class InvoiceItem extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price_amount',
        'unit_price_currency',
        'total_amount',
        'total_currency',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
