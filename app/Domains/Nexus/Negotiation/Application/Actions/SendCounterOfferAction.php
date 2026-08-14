<?php

namespace App\Domains\Nexus\Negotiation\Application\Actions;

use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use App\Domains\Nexus\Negotiation\Application\Services\NegotiationReasoningService;
use App\Domains\Nexus\Negotiation\Domain\Entities\NegotiationMessage;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationMessageWasRecorded;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationMessageType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Either party may counter while a Negotiation is still open (Proposed or
 * Countered) — turn-taking is not enforced (nothing in the roadmap asks
 * for strict alternation, and an Agent countering its own last offer
 * again is a harmless no-op the round-limit guard already bounds).
 */
final class SendCounterOfferAction
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
        private readonly NegotiationMessageRepositoryInterface $messages,
        private readonly NegotiationReasoningService $reasoning,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    public function execute(int $negotiationId, int $actingBusinessId, NegotiationTerms $terms): NegotiationData
    {
        $negotiation = $this->negotiations->findById($negotiationId);

        if (! $negotiation) {
            throw new InvalidArgumentException("Negotiation [{$negotiationId}] does not exist.");
        }

        if (! $negotiation->isParty($actingBusinessId)) {
            throw new InvalidArgumentException("Business [{$actingBusinessId}] is not a party to this Negotiation.");
        }

        $previousTerms = $negotiation->currentTerms();
        $roundCountBeforeCounter = $negotiation->roundCount();

        // Round-limit is checked inside counter() — charge only once the
        // counter-offer is actually accepted onto the Negotiation, never
        // for a request that's about to be rejected as invalid.
        $negotiation->counter($terms);
        $this->costGate->execute($actingBusinessId, 'nexus.negotiation.counter', $negotiationId);
        $negotiation = $this->negotiations->save($negotiation);

        $message = $this->messages->save(NegotiationMessage::record(
            negotiationId: $negotiation->id(),
            senderBusinessId: $actingBusinessId,
            type: NegotiationMessageType::Counter,
            terms: $terms,
            reasoning: $this->reasoning->forCounter($previousTerms, $terms, $roundCountBeforeCounter, $negotiation->maxRounds()),
        ));

        Event::dispatch(new NegotiationMessageWasRecorded($negotiation, $message));

        return NegotiationData::fromEntity($negotiation);
    }
}
