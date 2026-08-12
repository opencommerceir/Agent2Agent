<?php

namespace App\Domains\Nexus\Growth\Domain\Repositories;

use App\Domains\Nexus\Growth\Domain\Entities\CoalitionMember;

interface CoalitionMemberRepositoryInterface
{
    /**
     * @return list<CoalitionMember>
     */
    public function findByCoalitionId(int $coalitionId): array;

    public function findByCoalitionAndBusiness(int $coalitionId, int $businessId): ?CoalitionMember;

    public function save(CoalitionMember $member): CoalitionMember;

    public function delete(int $id): void;
}
