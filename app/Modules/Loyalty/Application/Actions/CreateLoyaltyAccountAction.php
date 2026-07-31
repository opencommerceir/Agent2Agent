<?php

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Loyalty\Application\DTOs\LoyaltyAccountData;
use App\Modules\Loyalty\Domain\Entities\LoyaltyAccount;
use App\Modules\Loyalty\Domain\Exceptions\LoyaltyAccountAlreadyExistsException;
use App\Modules\Loyalty\Domain\Repositories\LoyaltyAccountRepositoryInterface;
use App\Modules\Loyalty\Domain\Exceptions\CustomerNotFoundException;

/**
 * One Action = one business operation: open a LoyaltyAccount for a
 * Customer. Depends on Commerce's CustomerRepositoryInterface — an
 * Interface from another Domain Module's Domain layer, never its
 * Infrastructure/Model — to verify the Customer exists (the same
 * cross-module Dependency Inversion CRM's CreateTicketAction/Finance's
 * CreateInvoiceAction already established), throwing Loyalty's *own*
 * CustomerNotFoundException rather than Commerce's concrete one (same
 * reasoning as CRM's own CustomerNotFoundException docblock).
 *
 * Enforces rule §d.2 (one LoyaltyAccount per Customer per tenant)
 * explicitly, rather than letting `loyalty_accounts`' own unique
 * constraint surface as a raw database error.
 */
final class CreateLoyaltyAccountAction
{
    public function __construct(
        private readonly LoyaltyAccountRepositoryInterface $accounts,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function execute(int $tenantId, int $customerId): LoyaltyAccountData
    {
        if (! $this->customers->findById($customerId, $tenantId)) {
            throw new CustomerNotFoundException("Customer [{$customerId}] does not exist.");
        }

        if ($this->accounts->customerHasAccount($customerId, $tenantId)) {
            throw new LoyaltyAccountAlreadyExistsException("Customer [{$customerId}] already has a LoyaltyAccount.");
        }

        $account = LoyaltyAccount::open($tenantId, $customerId);
        $account = $this->accounts->save($account);

        return LoyaltyAccountData::fromEntity($account);
    }
}
