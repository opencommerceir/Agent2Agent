<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\SuspensionAppealRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessStatus;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionAppealStatus;
use App\Domains\Nexus\Business\Domain\ValueObjects\VerificationStatus as BusinessVerificationStatus;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\ListingVerificationStatus;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\ValueObjects\DisputeCaseStatus;
use App\Domains\Nexus\Contract\Domain\ValueObjects\EscrowStatus;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationStatus;

/**
 * Backs the admin home page ("what needs my attention right now" —
 * pending verifications, open disputes, suspended businesses/appeals,
 * active negotiations, disputed escrows, gross revenue) instead of the
 * plain redirect-to-Revenue DashboardController used since the Commerce
 * KPI home page it originally rendered was retired (Nexus Phase 0). Pure
 * read model across five domains, the same "reading for a display
 * projection, no mutation" reasoning GetBusinessDashboardAction's own
 * docblock (Phase 1/M6) already established for exactly this kind of
 * cross-domain aggregation.
 *
 * Every count here is a real repository query, never a cached or
 * estimated number — consistent with this codebase's repeated "never a
 * fake or guessed number" design principle (docs/nexus/nexus_handoff.md,
 * Phase 8's own summary).
 */
final class GetPlatformOverviewAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly ProductRepositoryInterface $products,
        private readonly ServiceRepositoryInterface $services,
        private readonly SuspensionAppealRepositoryInterface $appeals,
        private readonly DisputeCaseRepositoryInterface $disputeCases,
        private readonly EscrowRepositoryInterface $escrows,
        private readonly NegotiationRepositoryInterface $negotiations,
        private readonly GetRevenueDashboardAction $getRevenueDashboard,
    ) {
    }

    /**
     * @return array{
     *     pendingBusinessVerifications: int,
     *     pendingListingVerifications: int,
     *     suspendedBusinesses: int,
     *     pendingAppeals: int,
     *     openDisputes: int,
     *     disputedEscrows: int,
     *     activeNegotiations: int,
     *     grossRevenue: int,
     * }
     */
    public function execute(): array
    {
        $activeStatuses = [NegotiationStatus::Proposed, NegotiationStatus::Countered, NegotiationStatus::PendingApproval];

        $activeNegotiations = array_filter(
            $this->negotiations->findAll(),
            fn ($negotiation) => in_array($negotiation->status(), $activeStatuses, true),
        );

        return [
            'pendingBusinessVerifications' => count($this->businesses->findByVerificationStatus(BusinessVerificationStatus::Pending)),
            'pendingListingVerifications' => count($this->products->findByVerificationStatus(ListingVerificationStatus::Pending))
                + count($this->services->findByVerificationStatus(ListingVerificationStatus::Pending)),
            'suspendedBusinesses' => count($this->businesses->findByStatus(BusinessStatus::Suspended)),
            'pendingAppeals' => count($this->appeals->findByStatus(SuspensionAppealStatus::Pending)),
            'openDisputes' => count($this->disputeCases->findByStatus(DisputeCaseStatus::Open)),
            'disputedEscrows' => count($this->escrows->findByStatus(EscrowStatus::Disputed)),
            'activeNegotiations' => count($activeNegotiations),
            'grossRevenue' => $this->getRevenueDashboard->execute()['grossRevenue'],
        ];
    }
}
