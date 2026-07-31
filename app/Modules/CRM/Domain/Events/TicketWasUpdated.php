<?php

namespace App\Modules\CRM\Domain\Events;

use App\Modules\CRM\Domain\Entities\Ticket;
use App\Modules\CRM\Domain\ValueObjects\TicketStatus;

/**
 * Domain event: a fact that already happened. Dispatched after a
 * Ticket's status has been changed and persisted — carries the previous
 * status too, the same reasoning Commerce's OrderStatusChanged event
 * gives for not making a listener re-derive "what changed" from the
 * Ticket alone.
 */
final class TicketWasUpdated
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly TicketStatus $previousStatus,
    ) {
    }
}
