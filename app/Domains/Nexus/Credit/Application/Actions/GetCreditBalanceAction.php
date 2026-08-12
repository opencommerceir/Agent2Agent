<?php

namespace App\Domains\Nexus\Credit\Application\Actions;

use App\Domains\Nexus\Credit\Application\DTOs\CreditBalanceData;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use InvalidArgumentException;

/**
 * Backs the nexus.credit.balance MCP capability (Phase 3/M2) — the caller
 * is always an authenticated Agent, which can only exist for a Business
 * that has already been verified (CreateAgentForBusinessAction only ever
 * runs off BusinessWasVerified), and GrantStartingCreditsOnBusinessVerifiedListener
 * always opens a CreditBalance row in that same event — so a missing
 * balance here is a genuine inconsistency, not an expected "not ready yet"
 * state (unlike GetBusinessDashboardAction's own nullable lookup, which
 * can legitimately be called before verification).
 */
final class GetCreditBalanceAction
{
    public function __construct(
        private readonly CreditBalanceRepositoryInterface $balances,
    ) {
    }

    public function execute(int $businessId): CreditBalanceData
    {
        $balance = $this->balances->findByBusinessId($businessId);

        if (! $balance) {
            throw new InvalidArgumentException("Business [{$businessId}] has no credit balance yet.");
        }

        return CreditBalanceData::fromEntity($balance);
    }
}
