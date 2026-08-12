<?php

namespace App\Domains\Nexus\Negotiation\Application\Actions;

use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use App\Domains\Nexus\Negotiation\Application\Services\NegotiationReasoningService;
use App\Domains\Nexus\Negotiation\Domain\Entities\NegotiationMessage;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationWasAccepted;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationMessageType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationStatus;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * "Agentها نمی‌توانند بدون تأیید انسان اقدامات پرارزش انجام دهند"
 * (docs/nexus-roadmap.md, AI Implementation Rules §5) — the accepting
 * Agent's OWN authority_limits gate its OWN accept() call (not the
 * counterparty's — this is "can I autonomously commit MY business to
 * this," never a judgment about the other side). `max_deal_value` is
 * read in the same smallest-currency-unit convention as Money::amount()
 * (Agent::authorityLimits() is a free-form JSON bag; no VO enforces this
 * today). A Business with no authority_limits configured at all has no
 * gate — a deliberate "not opted in yet" default, not an oversight, since
 * SetAuthorityLimitsAction lets this stay null indefinitely.
 */
final class AcceptDealAction
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
        private readonly NegotiationMessageRepositoryInterface $messages,
        private readonly AgentRepositoryInterface $agents,
        private readonly NegotiationReasoningService $reasoning,
    ) {
    }

    public function execute(int $negotiationId, int $actingBusinessId): NegotiationData
    {
        $negotiation = $this->negotiations->findById($negotiationId);

        if (! $negotiation) {
            throw new InvalidArgumentException("Negotiation [{$negotiationId}] does not exist.");
        }

        if (! $negotiation->isParty($actingBusinessId)) {
            throw new InvalidArgumentException("Business [{$actingBusinessId}] is not a party to this Negotiation.");
        }

        $agent = $this->agents->findByBusinessId($actingBusinessId);
        $maxDealValue = $agent?->authorityLimits()['max_deal_value'] ?? null;
        $totalAmount = $negotiation->currentTerms()->totalAmount();
        $exceedsAuthorityLimit = $maxDealValue !== null && $totalAmount > $maxDealValue;

        if ($exceedsAuthorityLimit) {
            $negotiation->requestApproval();
        } else {
            $negotiation->accept();
        }

        $negotiation = $this->negotiations->save($negotiation);

        $this->messages->save(NegotiationMessage::record(
            negotiationId: $negotiation->id(),
            senderBusinessId: $actingBusinessId,
            type: NegotiationMessageType::Accept,
            terms: $negotiation->currentTerms(),
            reasoning: $this->reasoning->forAccept($negotiation->currentTerms(), $exceedsAuthorityLimit),
        ));

        if ($negotiation->status() === NegotiationStatus::Accepted) {
            Event::dispatch(new NegotiationWasAccepted($negotiation));
        }

        return NegotiationData::fromEntity($negotiation);
    }
}
