<?php

namespace App\Modules\CRM\Domain\Events;

use App\Modules\CRM\Domain\Entities\TicketComment;

/**
 * Domain event: a fact that already happened. Dispatched after a
 * TicketComment has been persisted.
 */
final class CommentWasAddedToTicket
{
    public function __construct(
        public readonly TicketComment $comment,
    ) {
    }
}
