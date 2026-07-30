<?php

namespace App\Core\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `tenants` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Core\Domain\Entities\Tenant instead.
 */
class Tenant extends Model
{
    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'status',
    ];
}
