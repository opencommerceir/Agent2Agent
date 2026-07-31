<?php

namespace App\Modules\CRM\Application\Actions;

use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\CRM\Application\DTOs\TicketData;
use App\Modules\CRM\Domain\Entities\Ticket;
use App\Modules\CRM\Domain\Events\TicketWasCreated;
use App\Modules\CRM\Domain\Exceptions\CustomerNotFoundException;
use App\Modules\CRM\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\CRM\Domain\ValueObjects\TicketPriority;
use Illuminate\Support\Facades\Event;

/**
 * One Action = one business operation: create a Ticket and dispatch the
 * corresponding domain event.
 *
 * Depends on Commerce's `CustomerRepositoryInterface` — an Interface from
 * another Domain Module's Domain layer, never its Infrastructure/Model —
 * to verify the Customer exists before a Ticket can reference it. This is
 * the same "two aggregates meet only through explicit ids and each
 * other's own Repository interface" pattern GetCustomerOrdersAction
 * already established *within* Commerce (HANDOFF §7.4), applied here
 * *across* modules instead. CRM never imports
 * `App\Modules\Commerce\Domain\Entities\Customer` or
 * `App\Modules\Commerce\Infrastructure\Models\Customer` — only the
 * Interface, and only to ask "does this id exist for this tenant".
 */
final class CreateTicketAction
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function execute(
        int $tenantId,
        int $agentId,
        int $customerId,
        string $subject,
        string $description,
        string $priority = 'medium',
    ): TicketData {
        if (! $this->customers->findById($customerId, $tenantId)) {
            throw new CustomerNotFoundException("Customer [{$customerId}] does not exist.");
        }

        $ticket = Ticket::create(
            tenantId: $tenantId,
            customerId: $customerId,
            agentId: $agentId,
            subject: $subject,
            description: $description,
            priority: TicketPriority::from($priority),
        );

        $ticket = $this->tickets->save($ticket);

        Event::dispatch(new TicketWasCreated($ticket));

        return TicketData::fromEntity($ticket);
    }
}
