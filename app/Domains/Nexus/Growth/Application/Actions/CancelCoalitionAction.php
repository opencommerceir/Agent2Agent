<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Growth\Application\DTOs\CoalitionData;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionMemberRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionRepositoryInterface;
use InvalidArgumentException;

/**
 * Organizer-only, from Forming or Negotiating (Coalition::ALLOWED_TRANSITIONS) —
 * the manual escape hatch for a coalition whose bulk Negotiation gets
 * rejected, since no NegotiationWasRejected event exists to close that loop
 * automatically (Coalition's own docblock).
 */
final class CancelCoalitionAction
{
    public function __construct(
        private readonly CoalitionRepositoryInterface $coalitions,
        private readonly CoalitionMemberRepositoryInterface $members,
    ) {
    }

    public function execute(int $coalitionId, int $actingBusinessId): CoalitionData
    {
        $coalition = $this->coalitions->findById($coalitionId);

        if (! $coalition) {
            throw new InvalidArgumentException("Coalition [{$coalitionId}] does not exist.");
        }

        if ($actingBusinessId !== $coalition->organizerBusinessId()) {
            throw new InvalidArgumentException('Only the organizer can cancel this coalition.');
        }

        $coalition->cancel();
        $coalition = $this->coalitions->save($coalition);

        return CoalitionData::fromEntity($coalition, $this->members->findByCoalitionId($coalitionId));
    }
}
