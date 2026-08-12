<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationStatus;
use InvalidArgumentException;

/**
 * A pure read-model spanning Business/Agent/Catalog/Credit/Negotiation —
 * the same role app/Modules/Analytics's own GetDashboardStatsAction plays
 * for the admin Dashboard (DashboardController calls it instead of
 * reading repositories directly). Reading across domains for a display
 * projection is not the same as one domain's write-side logic depending
 * on another's — no mutation happens here, so this doesn't violate
 * Inter-Module Communication (docs/modules.md); it's the query-side
 * counterpart.
 *
 * `creditBalance` reads the repository directly (not through
 * GetCreditBalanceAction, which throws on a missing row) — a Business seen
 * here can legitimately be unverified yet (no CreditBalance row opened
 * yet, since GrantStartingCreditsOnBusinessVerifiedListener only reacts to
 * BusinessWasVerified), so `null` here still means "not provisioned yet".
 */
final class GetBusinessDashboardAction
{
    /**
     * @var list<NegotiationStatus>
     */
    private const ACTIVE_STATUSES = [NegotiationStatus::Proposed, NegotiationStatus::Countered, NegotiationStatus::PendingApproval];

    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly AgentRepositoryInterface $agents,
        private readonly ProductRepositoryInterface $products,
        private readonly ServiceRepositoryInterface $services,
        private readonly CreditBalanceRepositoryInterface $creditBalances,
        private readonly NegotiationRepositoryInterface $negotiations,
    ) {
    }

    /**
     * @return array{
     *     business: \App\Domains\Nexus\Business\Domain\Entities\Business,
     *     agent: ?\App\Domains\Nexus\Agent\Domain\Entities\Agent,
     *     productCount: int,
     *     serviceCount: int,
     *     creditBalance: ?int,
     *     activeNegotiations: int,
     * }
     */
    public function execute(int $businessId): array
    {
        $business = $this->businesses->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $activeNegotiations = array_filter(
            $this->negotiations->findVisibleTo($businessId),
            fn ($negotiation) => in_array($negotiation->status(), self::ACTIVE_STATUSES, true),
        );

        return [
            'business' => $business,
            'agent' => $this->agents->findByBusinessId($businessId),
            'productCount' => count($this->products->findByBusinessId($businessId)),
            'serviceCount' => count($this->services->findByBusinessId($businessId)),
            'creditBalance' => $this->creditBalances->findByBusinessId($businessId)?->balance(),
            'activeNegotiations' => count($activeNegotiations),
        ];
    }
}
