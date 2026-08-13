<?php

namespace App\Domains\Nexus\Approval\Application\Actions;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalRequest;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalPolicyRepositoryInterface;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalRequestRepositoryInterface;

/**
 * Called by AcceptDealAction right after Negotiation::requestApproval()
 * trips — the ONLY new line that existing, otherwise-untouched Action
 * gains. A Business with no ApprovalPolicy gets no ApprovalRequest, which
 * is exactly Phase 2's original behavior: NegotiationViewerController falls
 * back to the existing ApprovePendingNegotiationAction/
 * RejectPendingNegotiationAction unchanged when none exists.
 */
final class OpenApprovalRequestForDealAction
{
    public function __construct(
        private readonly ApprovalPolicyRepositoryInterface $policies,
        private readonly ApprovalRequestRepositoryInterface $requests,
    ) {
    }

    public function execute(int $negotiationId, int $businessId, int $dealAmount): void
    {
        $policy = $this->policies->findByBusinessId($businessId);

        if (! $policy) {
            return;
        }

        $this->requests->save(ApprovalRequest::open(
            negotiationId: $negotiationId,
            businessId: $businessId,
            requiredLevels: $policy->levelsRequiredFor($dealAmount),
        ));
    }
}
