<?php

namespace App\Modules\CRM\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `tags` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\CRM\Domain\Entities\Tag instead.
 *
 * Deliberately has no `customers()` belongsToMany relation to Commerce's
 * Eloquent Customer model: even at the Infrastructure layer, CRM stays
 * decoupled from Commerce's Model classes (Dependency Inversion, per this
 * stage's explicit request) — EloquentTagRepository::assignToCustomer()
 * writes the `customer_tag` pivot row with a plain query builder insert
 * instead of an Eloquent relation.
 */
class Tag extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'tags';

    protected $fillable = [
        'tenant_id',
        'name',
        'color',
    ];
}
