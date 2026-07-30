<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent persistence model for the `products` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Commerce\Domain\Entities\Product
 * instead.
 */
class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'slug',
        'description',
        'sku',
        'price_amount',
        'price_currency',
        'status',
        'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
        'price_amount' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
