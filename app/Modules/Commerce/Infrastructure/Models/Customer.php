<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent persistence model for the `customers` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\Commerce\Domain\Entities\Customer
 * instead. SoftDeletes per this stage's explicit rule, even though no
 * DeleteCustomerAction was requested yet — the column/behavior is ready,
 * unused-by-an-Action-so-far same as Cart's Abandoned status.
 */
class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'status',
        'default_address',
        'notes',
    ];

    protected $casts = [
        'default_address' => 'array',
    ];
}
