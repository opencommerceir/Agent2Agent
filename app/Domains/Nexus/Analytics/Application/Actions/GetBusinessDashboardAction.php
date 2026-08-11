<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use InvalidArgumentException;

/**
 * A pure read-model spanning Business/Agent/Catalog — the same role
 * app/Modules/Analytics's own GetDashboardStatsAction plays for the admin
 * Dashboard (DashboardController calls it instead of reading repositories
 * directly). Reading across domains for a display projection is not the
 * same as one domain's write-side logic depending on another's — no
 * mutation happens here, so this doesn't violate Inter-Module
 * Communication (docs/modules.md); it's the query-side counterpart.
 *
 * Credit balance / active negotiations are honest placeholders (Phase
 * 2/3 don't exist yet) — this Action returns null for both rather than
 * a fake number, and BusinessDashboardController's view renders that as
 * "—", not "0" (0 would falsely claim a real, known value).
 */
final class GetBusinessDashboardAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly AgentRepositoryInterface $agents,
        private readonly ProductRepositoryInterface $products,
        private readonly ServiceRepositoryInterface $services,
    ) {
    }

    /**
     * @return array{
     *     business: \App\Domains\Nexus\Business\Domain\Entities\Business,
     *     agent: ?\App\Domains\Nexus\Agent\Domain\Entities\Agent,
     *     productCount: int,
     *     serviceCount: int,
     *     creditBalance: null,
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
            // Phase 3 (Credit domain) doesn't exist yet.
            'creditBalance' => null,
            // Phase 2 (Negotiation domain) doesn't exist yet.
            'activeNegotiations' => null,
        ];
    }
}
