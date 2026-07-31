<?php

namespace App\Modules\CRM\Domain\ValueObjects;

/**
 * Ticket::changeStatus() enforces the forward-only sequence this order
 * implies (Open -> InProgress -> Resolved -> Closed) — see that method's
 * docblock for why the enum's declaration order matters here.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
