<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Growth\Application\DTOs\CoalitionData;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionMemberRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\ValueObjects\CoalitionStatus;

/**
 * Discovery for coalitions still accepting members — excludes the caller's
 * own already-joined coalitions and, since a Business cannot be both the
 * target supplier and a member, coalitions the caller is the target of
 * (they'd never join a bulk order aimed at themselves).
 */
final class ListOpenCoalitionsAction
{
    public function __construct(
        private readonly CoalitionRepositoryInterface $coalitions,
        private readonly CoalitionMemberRepositoryInterface $members,
    ) {
    }

    /**
     * @return list<CoalitionData>
     */
    public function execute(int $callingBusinessId): array
    {
        $open = $this->coalitions->findByStatus(CoalitionStatus::Forming->value);

        $visible = array_filter(
            $open,
            fn ($coalition) => $coalition->targetBusinessId() !== $callingBusinessId
                && ! $this->members->findByCoalitionAndBusiness($coalition->id(), $callingBusinessId)
        );

        return array_values(array_map(
            fn ($coalition) => CoalitionData::fromEntity($coalition, $this->members->findByCoalitionId($coalition->id())),
            $visible,
        ));
    }
}
