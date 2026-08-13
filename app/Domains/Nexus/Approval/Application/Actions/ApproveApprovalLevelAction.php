<?php

namespace App\Domains\Nexus\Approval\Application\Actions;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalDecision;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalDecisionRepositoryInterface;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalRequestRepositoryInterface;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalDecisionOutcome;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalRequestStatus;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Negotiation\Application\Actions\ApprovePendingNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\GetNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use InvalidArgumentException;

/**
 * The level-aware counterpart to ApprovePendingNegotiationAction, which it
 * calls directly — unchanged — the moment the chain completes. Only the
 * owner holding the CURRENT level's required role, on the Business awaiting
 * approval, may decide here; a Manager can't approve a level requiring CFO,
 * and neither can act on behalf of the other party's Negotiation.
 */
final class ApproveApprovalLevelAction
{
    public function __construct(
        private readonly ApprovalRequestRepositoryInterface $requests,
        private readonly ApprovalDecisionRepositoryInterface $decisions,
        private readonly ApprovePendingNegotiationAction $approvePendingNegotiation,
        private readonly GetNegotiationAction $getNegotiation,
    ) {
    }

    public function execute(int $negotiationId, int $decidingOwnerId): NegotiationData
    {
        $request = $this->requests->findByNegotiationId($negotiationId);

        if (! $request) {
            throw new InvalidArgumentException("Negotiation [{$negotiationId}] has no multi-level approval chain.");
        }

        if ($request->status() !== ApprovalRequestStatus::Pending) {
            throw new InvalidArgumentException("Approval chain for Negotiation [{$negotiationId}] is no longer pending.");
        }

        $decider = BusinessOwner::query()->find($decidingOwnerId);

        if (! $decider || $decider->business_id !== $request->businessId() || $decider->role !== $request->currentRequiredRole()) {
            throw new InvalidArgumentException("Owner [{$decidingOwnerId}] cannot approve level [{$request->currentLevelIndex()}] of this chain.");
        }

        $this->decisions->save(ApprovalDecision::record(
            approvalRequestId: $request->id(),
            levelIndex: $request->currentLevelIndex(),
            roleRequired: $request->currentRequiredRole(),
            decidedByOwnerId: $decidingOwnerId,
            decision: ApprovalDecisionOutcome::Approved,
        ));

        $request->approveCurrentLevel();
        $request = $this->requests->save($request);

        if ($request->status() === ApprovalRequestStatus::Completed) {
            return $this->approvePendingNegotiation->execute($negotiationId, $request->businessId());
        }

        return $this->getNegotiation->execute($negotiationId, $request->businessId());
    }
}
