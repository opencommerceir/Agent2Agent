<?php

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Application\DTOs\LoyaltyAccountData;
use App\Modules\Loyalty\Domain\Exceptions\LoyaltyAccountNotFoundException;
use App\Modules\Loyalty\Domain\Repositories\LoyaltyAccountRepositoryInterface;

/**
 * A strict lookup by customer_id — throws LoyaltyAccountNotFoundException
 * if none exists yet, unlike EarnPointsAction's own find-or-create
 * behavior (that Action's docblock explains why the two verbs differ).
 */
final class GetLoyaltyAccountAction
{
    public function __construct(
        private readonly LoyaltyAccountRepositoryInterface $accounts,
    ) {
    }

    public function execute(int $tenantId, int $customerId): LoyaltyAccountData
    {
        $account = $this->accounts->findByCustomer($customerId, $tenantId);

        if (! $account) {
            throw new LoyaltyAccountNotFoundException("Customer [{$customerId}] has no LoyaltyAccount.");
        }

        return LoyaltyAccountData::fromEntity($account);
    }
}
