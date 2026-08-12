<?php

namespace App\Domains\Nexus\Negotiation\Application\Actions;

use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationMessageData;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use InvalidArgumentException;

/**
 * Backs the Live Negotiation Viewer's initial page load (M7). Re-checks
 * party authorization independently rather than trusting the caller
 * already did (GetNegotiationAction's own check) — the same
 * self-contained authorization every other Action in this codebase does.
 */
final class ListNegotiationMessagesAction
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
        private readonly NegotiationMessageRepositoryInterface $messages,
    ) {
    }

    /**
     * @return list<NegotiationMessageData>
     */
    public function execute(int $negotiationId, int $requestingBusinessId): array
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
            $this->messages->findByNegotiationId($negotiationId),
        );
    }
}
