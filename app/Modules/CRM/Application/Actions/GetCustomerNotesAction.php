<?php

namespace App\Modules\CRM\Application\Actions;

use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\CRM\Application\DTOs\CustomerNoteData;
use App\Modules\CRM\Domain\Entities\CustomerNote;
use App\Modules\CRM\Domain\Exceptions\CustomerNotFoundException;
use App\Modules\CRM\Domain\Repositories\CustomerNoteRepositoryInterface;

/**
 * Not wired to MCP this stage — no `crm.customer.note.list`-shaped
 * capability was among the 5 requested. Exercised directly in tests
 * instead (same "built, tested, not yet exposed" gap UpdateTicketAction's
 * docblock describes).
 */
final class GetCustomerNotesAction
{
    public function __construct(
        private readonly CustomerNoteRepositoryInterface $notes,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    /**
     * @return list<CustomerNoteData>
     */
    public function execute(int $customerId, int $tenantId): array
    {
        if (! $this->customers->findById($customerId, $tenantId)) {
            throw new CustomerNotFoundException("Customer [{$customerId}] does not exist.");
        }

        return array_map(
            fn (CustomerNote $note) => CustomerNoteData::fromEntity($note),
            $this->notes->listByCustomer($customerId, $tenantId),
        );
    }
}
