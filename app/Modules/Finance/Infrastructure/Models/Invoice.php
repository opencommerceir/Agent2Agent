<?php

namespace App\Modules\Finance\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent persistence model for the `invoices` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Finance\Domain\Entities\Invoice
 * instead. No `order_id`/`customer_id` Eloquent relations to Commerce's
 * Models — Finance stays decoupled from Commerce's Model classes even at
 * the Infrastructure layer (same reasoning CRM's Tag Model gives for not
 * having a `customers()` relation).
 */
class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'customer_id',
        'invoice_number',
        'status',
        'subtotal_amount',
        'subtotal_currency',
        'tax_amount',
        'tax_currency',
        'total_amount',
        'total_currency',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
