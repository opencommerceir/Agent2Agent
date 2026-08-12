<?php

namespace App\Domains\Nexus\Negotiation\Application\Actions;

use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use InvalidArgumentException;

/**
 * Read-only lookup, party-authorized — shared by nexus.negotiation.status
 * (M4) and the Live Negotiation Viewer (M7) rather than each reimplementing
 * the same "does this Negotiation exist, is the caller actually a party"
 * check.
 */
final class GetNegotiationAction
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
    ) {
    }

    public function execute(int $negotiationId, int $requestingBusinessId): NegotiationData
    {
        $negotiation = $this->negotiations->findById($negotiationId);

        if (! $negotiation) {
            throw new InvalidArgumentException("Negotiation [{$negotiationId}] does not exist.");
        }

        if (! $negotiation->isParty($requestingBusinessId)) {
            throw new InvalidArgumentException("Business [{$requestingBusinessId}] is not a party to this Negotiation.");
        }

        return NegotiationData::fromEntity($negotiation);
    }
}
