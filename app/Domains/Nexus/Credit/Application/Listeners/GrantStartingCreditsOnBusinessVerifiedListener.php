<?php

namespace App\Domains\Nexus\Credit\Application\Listeners;

use App\Domains\Nexus\Business\Domain\Events\BusinessWasVerified;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\Entities\CreditBalance;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;

/**
 * "هزینه هر اقدام از config/nexus/platform.php خوانده شود" implies every
 * verified Business needs a balance to spend from day one — same
 * event-driven shape as CreateAgentOnBusinessVerifiedListener (Phase 1/M3),
 * reacting to Business's own event rather than VerifyBusinessAction calling
 * into the Credit domain directly (Inter-Module Communication,
 * docs/modules.md).
 *
 * Always opens a CreditBalance row, even when the configured starting
 * balance is 0 — GrantCreditsAction->CreditBalance::credit() rejects a
 * non-positive amount, and downstream lookups (GetCreditBalanceAction,
 * every CostGate-gated Action) need the row to exist regardless of the
 * seeded amount.
 */
final class GrantStartingCreditsOnBusinessVerifiedListener
{
    public function __construct(
        private readonly GrantCreditsAction $grantCredits,
        private readonly CreditBalanceRepositoryInterface $balances,
    ) {
    }

    public function handle(BusinessWasVerified $event): void
    {
        $businessId = $event->business->id();
        $startingBalance = (int) config('nexus.platform.credit.starting_balance', 0);

        if ($startingBalance > 0) {
            $this->grantCredits->execute(
                businessId: $businessId,
                amount: $startingBalance,
                type: CreditTransactionType::AdminGrant,
                reason: 'business.verified.starting_balance',
            );

            return;
        }

        $this->balances->save(CreditBalance::open($businessId));
    }
}
