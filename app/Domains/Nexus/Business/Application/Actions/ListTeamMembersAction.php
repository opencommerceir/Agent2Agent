<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Application\DTOs\TeamMemberData;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;

final class ListTeamMembersAction
{
    /**
     * @return list<TeamMemberData>
     */
    public function execute(int $businessId): array
    {
        return BusinessOwner::query()
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->get()
            ->map(fn (BusinessOwner $owner) => TeamMemberData::fromModel($owner))
            ->all();
    }
}
