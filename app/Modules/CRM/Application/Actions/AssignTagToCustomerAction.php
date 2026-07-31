<?php

namespace App\Modules\CRM\Application\Actions;

use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\CRM\Domain\Exceptions\CustomerNotFoundException;
use App\Modules\CRM\Domain\Exceptions\TagNotFoundException;
use App\Modules\CRM\Domain\Repositories\TagRepositoryInterface;

/**
 * Not wired to MCP this stage — same "built, tested, not yet exposed" gap
 * UpdateTicketAction's docblock describes. Validates both sides of the
 * assignment exist *for this tenant* before touching the customer_tag
 * pivot: an unknown/cross-tenant tag id reports TagNotFoundException, an
 * unknown/cross-tenant customer id reports CustomerNotFoundException —
 * neither is allowed to fall through to a raw foreign-key failure
 * (HANDOFF gotcha #8 territory).
 */
final class AssignTagToCustomerAction
{
    public function __construct(
        private readonly TagRepositoryInterface $tags,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function execute(int $tenantId, int $customerId, int $tagId): void
    {
        $tag = $this->tags->findById($tagId, $tenantId);

        if (! $tag) {
            throw new TagNotFoundException("Tag [{$tagId}] does not exist.");
        }

        if (! $this->customers->findById($customerId, $tenantId)) {
            throw new CustomerNotFoundException("Customer [{$customerId}] does not exist.");
        }

        $this->tags->assignToCustomer($tagId, $customerId);
    }
}
