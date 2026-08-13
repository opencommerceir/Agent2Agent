<?php

namespace App\Domains\Nexus\Credit\Application\Actions;

use App\Domains\Nexus\Credit\Application\DTOs\HoldingCreditPoolData;
use App\Domains\Nexus\Credit\Domain\Repositories\HoldingCreditPoolRepositoryInterface;

/**
 * A pool that has never received a contribution has no row yet — same
 * "not provisioned" honesty GetBusinessDashboardAction already applies to
 * a Business's own CreditBalance, reported here as a plain 0 rather than
 * null since a Holding's pool balance is always a meaningful number on the
 * show page (unlike a Business that may not exist at all).
 */
final class GetHoldingPoolBalanceAction
{
    public function __construct(
        private readonly HoldingCreditPoolRepositoryInterface $pools,
    ) {
    }

    public function execute(int $holdingId): HoldingCreditPoolData
    {
        $pool = $this->pools->findByHoldingId($holdingId);

        return new HoldingCreditPoolData(
            holdingId: $holdingId,
            balance: $pool?->balance() ?? 0,
        );
    }
}
