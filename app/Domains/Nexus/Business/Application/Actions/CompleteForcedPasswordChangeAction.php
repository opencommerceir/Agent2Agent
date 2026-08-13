<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use InvalidArgumentException;

/**
 * The other half of InviteTeamMemberAction's temporary password —
 * `must_change_password` is only ever cleared here, so a freshly-invited
 * team member can't keep using the emailed temporary password indefinitely.
 */
final class CompleteForcedPasswordChangeAction
{
    public function execute(int $ownerId, string $newPassword): void
    {
        $owner = BusinessOwner::query()->find($ownerId);

        if (! $owner) {
            throw new InvalidArgumentException("Business owner [{$ownerId}] does not exist.");
        }

        $owner->password = $newPassword;
        $owner->must_change_password = false;
        $owner->save();
    }
}
