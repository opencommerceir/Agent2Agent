<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent persistence model for the `product_variants` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\ProductVariant instead.
 */
class ProductVariant extends Model
{
    use SoftDeletes;

    protected $table = 'product_variants';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'sku',
        'price_amount',
        'price_currency',
        'attributes',
        'image_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
