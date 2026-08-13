<?php

namespace App\Domains\Nexus\Approval\Application\Actions;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalDecision;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalDecisionRepositoryInterface;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalRequestRepositoryInterface;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalDecisionOutcome;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalRequestStatus;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Negotiation\Application\Actions\RejectPendingNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use InvalidArgumentException;

/**
 * The level-aware counterpart to RejectPendingNegotiationAction — a
 * rejection at ANY level ends the whole chain immediately (no need to
 * reach the final level first), same "one no vote is final" logic a real
 * approval chain needs.
 */
final class RejectApprovalLevelAction
{
    public function __construct(
        private readonly ApprovalRequestRepositoryInterface $requests,
        private readonly ApprovalDecisionRepositoryInterface $decisions,
        private readonly RejectPendingNegotiationAction $rejectPendingNegotiation,
    ) {
    }

    public function execute(int $negotiationId, int $decidingOwnerId, ?string $reason = null): NegotiationData
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
            throw new InvalidArgumentException("Owner [{$decidingOwnerId}] cannot reject level [{$request->currentLevelIndex()}] of this chain.");
        }

        $this->decisions->save(ApprovalDecision::record(
            approvalRequestId: $request->id(),
            levelIndex: $request->currentLevelIndex(),
            roleRequired: $request->currentRequiredRole(),
            decidedByOwnerId: $decidingOwnerId,
            decision: ApprovalDecisionOutcome::Rejected,
        ));

        $request->reject();
        $request = $this->requests->save($request);

        return $this->rejectPendingNegotiation->execute($negotiationId, $request->businessId(), $reason);
    }
}
