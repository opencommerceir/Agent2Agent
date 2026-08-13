<?php

namespace App\Domains\Nexus\Negotiation\Interfaces\Http\Controllers;

use App\Domains\Nexus\Approval\Application\Actions\ApproveApprovalLevelAction;
use App\Domains\Nexus\Approval\Application\Actions\RejectApprovalLevelAction;
use App\Domains\Nexus\Approval\Application\DTOs\ApprovalRequestData;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalRequestRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Contract\Application\Actions\DisputeEscrowAction;
use App\Domains\Nexus\Contract\Application\Actions\ReleaseEscrowAction;
use App\Domains\Nexus\Contract\Application\Actions\SubmitDisputeEvidenceAction;
use App\Domains\Nexus\Contract\Application\DTOs\EscrowData;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Negotiation\Application\Actions\ApprovePendingNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\GetNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\ListMyNegotiationsAction;
use App\Domains\Nexus\Negotiation\Application\Actions\ListNegotiationMessagesAction;
use App\Domains\Nexus\Negotiation\Application\Actions\PollNegotiationMessagesAction;
use App\Domains\Nexus\Negotiation\Application\Actions\RejectPendingNegotiationAction;
use App\Domains\Nexus\Reputation\Application\Actions\CalculateReputationScoreAction;
use App\Domains\Nexus\Reputation\Application\Actions\SubmitReviewAction;
use App\Domains\Nexus\Reputation\Domain\Repositories\ReviewRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The Live Negotiation Viewer (docs/nexus-roadmap.md, Phase 2) — a
 * Business owner watches their own Agent negotiate and approves/rejects
 * deals paused for human approval. Controllers stay thin: every method
 * resolves the acting Business id then delegates to an Action, which
 * re-checks party authorization itself (Actions rule, not this
 * controller's job to enforce).
 */
class NegotiationViewerController extends Controller
{
    public function __construct(
        private readonly ListMyNegotiationsAction $listMyNegotiations,
        private readonly GetNegotiationAction $getNegotiation,
        private readonly ListNegotiationMessagesAction $listMessages,
        private readonly PollNegotiationMessagesAction $pollMessages,
        private readonly ApprovePendingNegotiationAction $approvePending,
        private readonly RejectPendingNegotiationAction $rejectPending,
        private readonly BusinessRepositoryInterface $businesses,
        private readonly EscrowRepositoryInterface $escrows,
        private readonly ReleaseEscrowAction $releaseEscrow,
        private readonly DisputeEscrowAction $disputeEscrow,
        private readonly ReviewRepositoryInterface $reviews,
        private readonly SubmitReviewAction $submitReview,
        private readonly CalculateReputationScoreAction $reputationScore,
        private readonly DisputeCaseRepositoryInterface $disputeCases,
        private readonly SubmitDisputeEvidenceAction $submitDisputeEvidence,
        private readonly ApprovalRequestRepositoryInterface $approvalRequests,
        private readonly ApproveApprovalLevelAction $approveApprovalLevel,
        private readonly RejectApprovalLevelAction $rejectApprovalLevel,
    ) {
    }

    public function index(): View
    {
        $negotiations = $this->listMyNegotiations->execute($this->actingBusinessId());

        return view('nexus::negotiations.index', ['negotiations' => $negotiations]);
    }

    public function show(int $negotiation): View
    {
        $businessId = $this->actingBusinessId();
        $negotiationData = $this->getNegotiation->execute($negotiation, $businessId);
        $messages = $this->listMessages->execute($negotiation, $businessId);

        $otherPartyId = $negotiationData->initiatorBusinessId === $businessId
            ? $negotiationData->counterpartyBusinessId
            : $negotiationData->initiatorBusinessId;
        $otherParty = $this->businesses->findById($otherPartyId);
        $escrow = $this->escrows->findByNegotiationId($negotiation);
        $myReview = $this->reviews->findByNegotiationAndReviewer($negotiation, $businessId);
        $disputeCase = $escrow ? $this->disputeCases->findByEscrowId($escrow->id()) : null;
        $approvalRequest = $this->approvalRequests->findByNegotiationId($negotiation);
        $callingOwnerRole = Auth::guard('business')->user()?->role?->value;

        return view('nexus::negotiations.show', [
            'negotiation' => $negotiationData,
            'messages' => $messages,
            'actingBusinessId' => $businessId,
            'otherPartyNameFa' => $otherParty?->nameFa() ?? '—',
            'otherPartyNameEn' => $otherParty?->nameEn() ?? '—',
            'otherPartyReputation' => $otherParty ? $this->reputationScore->execute($otherPartyId) : null,
            'escrow' => $escrow ? EscrowData::fromEntity($escrow) : null,
            'myReview' => $myReview,
            'disputeCase' => $disputeCase,
            'approvalRequest' => $approvalRequest ? ApprovalRequestData::fromEntity($approvalRequest) : null,
            'callingOwnerRole' => $callingOwnerRole,
        ]);
    }

    public function messages(int $negotiation, Request $request): JsonResponse
    {
        $businessId = $this->actingBusinessId();
        $afterId = (int) $request->integer('after', 0);
        $messages = $this->pollMessages->execute($negotiation, $businessId, $afterId);

        return response()->json(['messages' => array_map(fn ($m) => $m->toArray(), $messages)]);
    }

    /**
     * Phase 7/M4 — a Business with a configured ApprovalPolicy routes
     * through the level-aware Actions instead (which check the deciding
     * owner's OWN id, not just the Business's `pendingApprovalBusinessId`);
     * a Business with none keeps calling the original, untouched
     * ApprovePendingNegotiationAction exactly as before.
     */
    public function approve(int $negotiation): RedirectResponse
    {
        if ($this->approvalRequests->findByNegotiationId($negotiation)) {
            $this->approveApprovalLevel->execute($negotiation, Auth::guard('business')->id());
        } else {
            $this->approvePending->execute($negotiation, $this->actingBusinessId());
        }

        return redirect()->route('nexus.negotiations.show', $negotiation);
    }

    public function reject(int $negotiation, Request $request): RedirectResponse
    {
        $reason = $request->string('reason')->toString() ?: null;

        if ($this->approvalRequests->findByNegotiationId($negotiation)) {
            $this->rejectApprovalLevel->execute($negotiation, Auth::guard('business')->id(), $reason);
        } else {
            $this->rejectPending->execute($negotiation, $this->actingBusinessId(), $reason);
        }

        return redirect()->route('nexus.negotiations.show', $negotiation);
    }

    public function releaseEscrow(int $negotiation): RedirectResponse
    {
        $this->releaseEscrow->execute($negotiation, $this->actingBusinessId());

        return redirect()->route('nexus.negotiations.show', $negotiation);
    }

    public function disputeEscrow(int $negotiation, Request $request): RedirectResponse
    {
        $this->disputeEscrow->execute($negotiation, $this->actingBusinessId(), $request->string('reason')->toString() ?: null);

        return redirect()->route('nexus.negotiations.show', $negotiation);
    }

    public function submitDisputeEvidence(int $negotiation, Request $request): RedirectResponse
    {
        $escrow = $this->escrows->findByNegotiationId($negotiation);
        $disputeCase = $escrow ? $this->disputeCases->findByEscrowId($escrow->id()) : null;

        if ($disputeCase) {
            $this->submitDisputeEvidence->execute($disputeCase->id(), $this->actingBusinessId(), $request->string('note')->toString());
        }

        return redirect()->route('nexus.negotiations.show', $negotiation);
    }

    public function submitReview(int $negotiation, Request $request): RedirectResponse
    {
        $this->submitReview->execute(
            $negotiation,
            $this->actingBusinessId(),
            $request->integer('rating'),
            $request->string('comment')->toString() ?: null,
        );

        return redirect()->route('nexus.negotiations.show', $negotiation);
    }

    private function actingBusinessId(): int
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return $owner->business_id;
    }
}
