<?php

namespace App\Domains\Nexus\Negotiation\Application\Actions;

use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationMessageData;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use InvalidArgumentException;

/**
 * Backs the Live Negotiation Viewer's polling endpoint (M7 decision #8 —
 * plain setInterval + fetch, no WebSocket/broadcast infrastructure
 * exists in this codebase). Returns only messages after $afterMessageId
 * so the client-side poll stays cheap.
 */
final class PollNegotiationMessagesAction
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
        private readonly NegotiationMessageRepositoryInterface $messages,
    ) {
    }

    /**
     * @return list<NegotiationMessageData>
     */
    public function execute(int $negotiationId, int $requestingBusinessId, int $afterMessageId): array
    {
        $negotiation = $this->negotiations->findById($negotiationId);

        if (! $negotiation) {
            throw new InvalidArgumentException("Negotiation [{$negotiationId}] does not exist.");
        }

        if (! $negotiation->isParty($requestingBusinessId)) {
            throw new InvalidArgumentException("Business [{$requestingBusinessId}] is not a party to this Negotiation.");
        }

        return array_map(
            fn ($message) => NegotiationMessageData::fromEntity($message),
            $this->messages->findAfter($negotiationId, $afterMessageId),
        );
    }
}
