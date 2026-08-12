<?php

namespace App\Domains\Nexus\Negotiation\Application\Actions;

use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use App\Domains\Nexus\Negotiation\Application\Services\NegotiationReasoningService;
use App\Domains\Nexus\Negotiation\Domain\Entities\NegotiationMessage;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationMessageType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
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

        $negotiation->counter($terms);
        $negotiation = $this->negotiations->save($negotiation);

        $this->messages->save(NegotiationMessage::record(
            negotiationId: $negotiation->id(),
            senderBusinessId: $actingBusinessId,
            type: NegotiationMessageType::Counter,
            terms: $terms,
            reasoning: $this->reasoning->forCounter($previousTerms, $terms, $roundCountBeforeCounter, $negotiation->maxRounds()),
        ));

        return NegotiationData::fromEntity($negotiation);
    }
}
