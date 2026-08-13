<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use InvalidArgumentException;

final class RemoveTeamMemberAction
{
    public function execute(int $businessId, int $callingOwnerId, int $targetOwnerId): void
    {
        $caller = BusinessOwner::query()->find($callingOwnerId);

        if (! $caller || $caller->business_id !== $businessId || $caller->role !== TeamMemberRole::Owner) {
            throw new InvalidArgumentException('Only an Owner may remove a team member.');
        }

        $target = BusinessOwner::query()->find($targetOwnerId);

        if (! $target || $target->business_id !== $businessId) {
            throw new InvalidArgumentException("Team member [{$targetOwnerId}] does not belong to this Business.");
        }

        if ($target->role === TeamMemberRole::Owner && $this->isLastOwner($businessId, $targetOwnerId)) {
            throw new InvalidArgumentException('Cannot remove the last remaining Owner.');
        }

        $target->delete();
    }

    private function isLastOwner(int $businessId, int $excludingOwnerId): bool
    {
        return BusinessOwner::query()
            ->where('business_id', $businessId)
            ->where('role', TeamMemberRole::Owner->value)
            ->where('id', '!=', $excludingOwnerId)
            ->doesntExist();
    }
}
