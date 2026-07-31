<?php

namespace App\Modules\CRM\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `ticket_comments` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Modules\CRM\Domain\Entities\TicketComment
 * instead. No `updated_at` — comments are immutable — only `created_at`
 * (see the migration).
 */
class TicketComment extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ticket_comments';

    protected $fillable = [
        'ticket_id',
        'agent_id',
        'content',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
