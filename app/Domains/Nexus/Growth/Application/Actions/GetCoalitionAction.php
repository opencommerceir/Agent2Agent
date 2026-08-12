<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Growth\Application\DTOs\CoalitionData;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionMemberRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionRepositoryInterface;
use InvalidArgumentException;

final class GetCoalitionAction
{
    public function __construct(
        private readonly CoalitionRepositoryInterface $coalitions,
        private readonly CoalitionMemberRepositoryInterface $members,
    ) {
    }

    public function execute(int $coalitionId): CoalitionData
    {
        $coalition = $this->coalitions->findById($coalitionId);

        if (! $coalition) {
            throw new InvalidArgumentException("Coalition [{$coalitionId}] does not exist.");
        }

        return CoalitionData::fromEntity($coalition, $this->members->findByCoalitionId($coalitionId));
    }
}
