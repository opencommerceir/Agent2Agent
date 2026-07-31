<?php

namespace App\Modules\CRM\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent persistence model for the `tickets` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\CRM\Domain\Entities\Ticket instead.
 */
class Ticket extends Model
{
    use SoftDeletes;

    protected $table = 'tickets';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'agent_id',
        'subject',
        'description',
        'status',
        'priority',
    ];

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }
}
