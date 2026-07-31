<?php

namespace App\Modules\CRM\Application\Actions;

use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\CRM\Application\DTOs\CustomerNoteData;
use App\Modules\CRM\Domain\Entities\CustomerNote;
use App\Modules\CRM\Domain\Events\NoteWasAddedToCustomer;
use App\Modules\CRM\Domain\Exceptions\CustomerNotFoundException;
use App\Modules\CRM\Domain\Repositories\CustomerNoteRepositoryInterface;
use Illuminate\Support\Facades\Event;

/**
 * Backs the `crm.note.create` MCP capability. Same cross-module
 * Customer-existence check as CreateTicketAction — see that Action's
 * docblock for the full reasoning.
 */
final class AddNoteToCustomerAction
{
    public function __construct(
        private readonly CustomerNoteRepositoryInterface $notes,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function execute(int $tenantId, int $agentId, int $customerId, string $content): CustomerNoteData
    {
        if (! $this->customers->findById($customerId, $tenantId)) {
            throw new CustomerNotFoundException("Customer [{$customerId}] does not exist.");
        }

        $note = CustomerNote::create($tenantId, $customerId, $agentId, $content);
        $note = $this->notes->save($note);

        Event::dispatch(new NoteWasAddedToCustomer($note));

        return CustomerNoteData::fromEntity($note);
    }
}
