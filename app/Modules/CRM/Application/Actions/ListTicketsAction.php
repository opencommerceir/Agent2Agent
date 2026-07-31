<?php

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\DTOs\TicketData;
use App\Modules\CRM\Domain\Entities\Ticket;
use App\Modules\CRM\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\CRM\Domain\ValueObjects\TicketStatus;

/**
 * Backs the `crm.ticket.list` MCP capability — takes the raw
 * `array $input` MCP Gateway received plus tenantId, doubling directly
 * as the callable CRMServiceProvider::boot() registers, the same
 * pattern Commerce's ListProductsAction/ListOrdersAction established.
 */
final class ListTicketsAction
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{tickets: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $status = isset($input['status']) && is_string($input['status'])
            ? TicketStatus::tryFrom($input['status'])
            : null;

        $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : null;

        $limit = isset($input['limit']) && is_int($input['limit'])
            ? max(1, min($input['limit'], self::MAX_LIMIT))
            : self::DEFAULT_LIMIT;

        $tickets = $this->tickets->list($tenantId, $status, $customerId, $limit);

        return [
            'tickets' => array_map(
                fn (Ticket $ticket) => TicketData::fromEntity($ticket)->toArray(),
                $tickets,
            ),
        ];
    }
}
