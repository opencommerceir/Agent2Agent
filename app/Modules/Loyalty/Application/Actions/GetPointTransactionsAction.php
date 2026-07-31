<?php

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Application\DTOs\PointTransactionData;
use App\Modules\Loyalty\Domain\Exceptions\LoyaltyAccountNotFoundException;
use App\Modules\Loyalty\Domain\Repositories\LoyaltyAccountRepositoryInterface;
use App\Modules\Loyalty\Domain\Repositories\PointTransactionRepositoryInterface;

/**
 * Backs `loyalty.transaction.list`. Takes customer_id (not
 * loyalty_account_id) since that's what an Agent naturally has on hand —
 * resolves the LoyaltyAccount first, the same "look the parent up by the
 * externally-known id, then query its children" shape
 * GetCustomerOrdersAction already established within Commerce.
 */
final class GetPointTransactionsAction
{
    private const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly LoyaltyAccountRepositoryInterface $accounts,
        private readonly PointTransactionRepositoryInterface $transactions,
    ) {
    }

    /**
     * @return array{transactions: list<array<string, mixed>>}
     */
    public function execute(int $tenantId, int $customerId, ?int $limit = null): array
    {
        $account = $this->accounts->findByCustomer($customerId, $tenantId);

        if (! $account) {
            throw new LoyaltyAccountNotFoundException("Customer [{$customerId}] has no LoyaltyAccount.");
        }

        $transactions = $this->transactions->listByAccount($account->id(), $tenantId, $limit ?? self::DEFAULT_LIMIT);

        return [
            'transactions' => array_map(
                fn ($transaction) => PointTransactionData::fromEntity($transaction)->toArray(),
                $transactions,
            ),
        ];
    }
}
