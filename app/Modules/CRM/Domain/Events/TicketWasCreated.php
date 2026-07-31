<?php

namespace App\Modules\CRM\Domain\Events;

use App\Modules\CRM\Domain\Entities\Ticket;

/**
 * Domain event: a fact that already happened. Dispatched after a Ticket
 * has been persisted.
 */
final class TicketWasCreated
{
    public function __construct(
        public readonly Ticket $ticket,
    ) {
    }
}
