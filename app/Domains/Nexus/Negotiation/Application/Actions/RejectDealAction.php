<?php

namespace App\Domains\Nexus\Negotiation\Application\Actions;

use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use App\Domains\Nexus\Negotiation\Domain\Entities\NegotiationMessage;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationMessageType;
use InvalidArgumentException;

final class RejectDealAction
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
        private readonly NegotiationMessageRepositoryInterface $messages,
    ) {
    }

    public function execute(int $negotiationId, int $actingBusinessId, ?string $reason = null): NegotiationData
    {
        $negotiation = $this->negotiations->findById($negotiationId);

        if (! $negotiation) {
            throw new InvalidArgumentException("Negotiation [{$negotiationId}] does not exist.");
        }

        if (! $negotiation->isParty($actingBusinessId)) {
            throw new InvalidArgumentException("Business [{$actingBusinessId}] is not a party to this Negotiation.");
        }

        $negotiation->reject($reason);
        $negotiation = $this->negotiations->save($negotiation);

        $this->messages->save(NegotiationMessage::record(
            negotiationId: $negotiation->id(),
            senderBusinessId: $actingBusinessId,
            type: NegotiationMessageType::Reject,
            terms: $negotiation->currentTerms(),
        ));

        return NegotiationData::fromEntity($negotiation);
    }
}
