<?php

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Domain\Repositories\LoyaltyAccountRepositoryInterface;
use DateTimeImmutable;

/**
 * The per-tenant unit the scheduled `loyalty:expire-points` command
 * (HANDOFF §8.23/§8.27) iterates over. ExpirePointsAction itself stays
 * single-account (its own docblock already documents why); this Action
 * is the thin fan-out that lists every LoyaltyAccount for one tenant
 * (LoyaltyAccountRepositoryInterface::allForTenant(), added alongside
 * this Action) and calls ExpirePointsAction once per account, same
 * "Actions composing Actions" pattern used throughout this codebase.
 */
final class BulkExpirePointsAction
{
    public function __construct(
        private readonly LoyaltyAccountRepositoryInterface $accounts,
        private readonly ExpirePointsAction $expirePoints,
    ) {
    }

    /**
     * @return array{accounts_checked: int, accounts_affected: int, transactions_created: int}
     */
    public function execute(int $tenantId, ?DateTimeImmutable $asOf = null): array
    {
        $accounts = $this->accounts->allForTenant($tenantId);

        $accountsAffected = 0;
        $transactionsCreated = 0;

        foreach ($accounts as $account) {
            $expired = $this->expirePoints->execute($tenantId, $account->id(), $asOf);

            if ($expired !== []) {
                $accountsAffected++;
                $transactionsCreated += count($expired);
            }
        }

        return [
            'accounts_checked' => count($accounts),
            'accounts_affected' => $accountsAffected,
            'transactions_created' => $transactionsCreated,
        ];
    }
}
