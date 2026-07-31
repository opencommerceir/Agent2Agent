<?php

namespace App\Modules\CRM\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `customer_notes` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\CRM\Domain\Entities\CustomerNote
 * instead. No `updated_at` — notes are immutable — only `created_at`
 * (see the migration).
 */
class CustomerNote extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'customer_notes';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'agent_id',
        'content',
    ];
}
