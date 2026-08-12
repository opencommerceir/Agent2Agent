<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use InvalidArgumentException;

/**
 * A pure read-model spanning Business/Agent/Catalog/Credit — the same role
 * app/Modules/Analytics's own GetDashboardStatsAction plays for the admin
 * Dashboard (DashboardController calls it instead of reading repositories
 * directly). Reading across domains for a display projection is not the
 * same as one domain's write-side logic depending on another's — no
 * mutation happens here, so this doesn't violate Inter-Module
 * Communication (docs/modules.md); it's the query-side counterpart.
 *
 * `creditBalance` reads the repository directly (not through
 * GetCreditBalanceAction, which throws on a missing row) — a Business seen
 * here can legitimately be unverified yet (no CreditBalance row opened
 * yet, since GrantStartingCreditsOnBusinessVerifiedListener only reacts to
 * BusinessWasVerified), so `null` here still means "not provisioned yet",
 * same honest-placeholder convention `activeNegotiations` keeps until
 * Phase 3/M6 fills it in.
 */
final class GetBusinessDashboardAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly AgentRepositoryInterface $agents,
        private readonly ProductRepositoryInterface $products,
        private readonly ServiceRepositoryInterface $services,
        private readonly CreditBalanceRepositoryInterface $creditBalances,
    ) {
    }

    /**
     * @return array{
     *     business: \App\Domains\Nexus\Business\Domain\Entities\Business,
     *     agent: ?\App\Domains\Nexus\Agent\Domain\Entities\Agent,
     *     productCount: int,
     *     serviceCount: int,
     *     creditBalance: ?int,
     *     activeNegotiations: null,
     * }
     */
    public function execute(int $businessId): array
    {
        $business = $this->businesses->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        return [
            'business' => $business,
            'agent' => $this->agents->findByBusinessId($businessId),
            'productCount' => count($this->products->findByBusinessId($businessId)),
            'serviceCount' => count($this->services->findByBusinessId($businessId)),
            'creditBalance' => $this->creditBalances->findByBusinessId($businessId)?->balance(),
            // Phase 3/M6 (Revenue Dashboard) fills this in.
            'activeNegotiations' => null,
        ];
    }
}
