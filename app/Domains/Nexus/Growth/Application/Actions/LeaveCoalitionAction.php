<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionMemberRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\ValueObjects\CoalitionStatus;
use InvalidArgumentException;

/**
 * The organizer cannot leave their own Coalition (CancelCoalitionAction is
 * the organizer's own exit path) — a coalition with no organizer would have
 * no one authorized to close() or cancel() it.
 */
final class LeaveCoalitionAction
{
    public function __construct(
        private readonly CoalitionRepositoryInterface $coalitions,
        private readonly CoalitionMemberRepositoryInterface $members,
    ) {
    }

    public function execute(int $coalitionId, int $businessId): void
    {
        $coalition = $this->coalitions->findById($coalitionId);

        if (! $coalition) {
            throw new InvalidArgumentException("Coalition [{$coalitionId}] does not exist.");
        }

        if ($coalition->status() !== CoalitionStatus::Forming) {
            throw new InvalidArgumentException("Coalition [{$coalitionId}] is no longer accepting membership changes.");
        }

        if ($businessId === $coalition->organizerBusinessId()) {
            throw new InvalidArgumentException('The organizer cannot leave their own coalition — cancel it instead.');
        }

        $member = $this->members->findByCoalitionAndBusiness($coalitionId, $businessId);

        if (! $member) {
            throw new InvalidArgumentException("Business [{$businessId}] is not a member of coalition [{$coalitionId}].");
        }

        $this->members->delete($member->id());
    }
}
