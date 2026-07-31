<?php

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\DTOs\TicketData;
use App\Modules\CRM\Domain\Exceptions\TicketNotFoundException;
use App\Modules\CRM\Domain\Repositories\TicketRepositoryInterface;

/**
 * Backs the `crm.ticket.get` MCP capability. Tenant-scoped by
 * TicketRepositoryInterface::findById() itself — an id belonging to a
 * different tenant reports the same TicketNotFoundException as an id
 * that never existed at all, never a distinguishable "forbidden" (same
 * tenant-isolation-by-omission shape Commerce's own findById()s use
 * everywhere).
 */
final class GetTicketAction
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
    ) {
    }

    public function execute(int $id, int $tenantId): TicketData
    {
        $ticket = $this->tickets->findById($id, $tenantId);

        if (! $ticket) {
            throw new TicketNotFoundException("Ticket [{$id}] does not exist.");
        }

        return TicketData::fromEntity($ticket);
    }
}
