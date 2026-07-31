<?php

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\DTOs\TicketData;
use App\Modules\CRM\Domain\Events\TicketWasUpdated;
use App\Modules\CRM\Domain\Exceptions\TicketNotFoundException;
use App\Modules\CRM\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\CRM\Domain\ValueObjects\TicketStatus;
use Illuminate\Support\Facades\Event;

/**
 * The Ticket status transition — Ticket::changeStatus() itself enforces
 * the forward-only sequence, so this Action is only ever a thin
 * findById -> changeStatus -> save -> dispatch wrapper (same shape as
 * Commerce's UpdateOrderStatusAction).
 *
 * Deliberately NOT wired to MCP this stage — only the 5 capabilities the
 * request specified were wired (`crm.ticket.create/get/list`,
 * `crm.comment.create`, `crm.note.create`); a general ticket-update
 * capability wasn't among them. Exercised directly in tests instead, the
 * same "fully built, fully tested, not yet exposed to Agents" gap several
 * Commerce Actions already have (HANDOFF §6/§8.2) — a one-capability-
 * definition-plus-one-handler-closure addition whenever it's needed.
 */
final class UpdateTicketAction
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
    ) {
    }

    public function execute(int $id, int $tenantId, string $status): TicketData
    {
        $ticket = $this->tickets->findById($id, $tenantId);

        if (! $ticket) {
            throw new TicketNotFoundException("Ticket [{$id}] does not exist.");
        }

        $previousStatus = $ticket->status();

        $ticket->changeStatus(TicketStatus::from($status)); // throws InvalidTicketStatusException, or ValueError for an unknown status string

        $ticket = $this->tickets->save($ticket);

        Event::dispatch(new TicketWasUpdated($ticket, $previousStatus));

        return TicketData::fromEntity($ticket);
    }
}
