<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent persistence model for the `categories` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Commerce\Domain\Entities\Category
 * instead.
 */
class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
