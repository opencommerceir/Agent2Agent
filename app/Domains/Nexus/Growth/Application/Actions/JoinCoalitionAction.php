<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Growth\Application\DTOs\CoalitionData;
use App\Domains\Nexus\Growth\Domain\Entities\CoalitionMember;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionMemberRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\ValueObjects\CoalitionStatus;
use InvalidArgumentException;

final class JoinCoalitionAction
{
    public function __construct(
        private readonly CoalitionRepositoryInterface $coalitions,
        private readonly CoalitionMemberRepositoryInterface $members,
    ) {
    }

    public function execute(int $coalitionId, int $businessId, int $quantity): CoalitionData
    {
        $coalition = $this->coalitions->findById($coalitionId);

        if (! $coalition) {
            throw new InvalidArgumentException("Coalition [{$coalitionId}] does not exist.");
        }

        if ($coalition->status() !== CoalitionStatus::Forming) {
            throw new InvalidArgumentException("Coalition [{$coalitionId}] is no longer accepting members.");
        }

        if ($businessId === $coalition->targetBusinessId()) {
            throw new InvalidArgumentException('The target supplier cannot join its own coalition.');
        }

        if ($this->members->findByCoalitionAndBusiness($coalitionId, $businessId)) {
            throw new InvalidArgumentException("Business [{$businessId}] has already joined coalition [{$coalitionId}].");
        }

        $this->members->save(CoalitionMember::join($coalitionId, $businessId, $quantity));

        return CoalitionData::fromEntity($coalition, $this->members->findByCoalitionId($coalitionId));
    }
}
