<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwnerRecoveryCode;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Requires the owner's own current password — same weight as the OAuth
 * "link account" confirmation (Phase 7/M6): turning off a security control
 * must prove present-tense ownership of the account, not just an active
 * session.
 */
final class DisableMfaAction
{
    public function execute(int $ownerId, string $password): void
    {
        $owner = BusinessOwner::query()->find($ownerId);

        if (! $owner) {
            throw new InvalidArgumentException("Business owner [{$ownerId}] does not exist.");
        }

        if (! Hash::check($password, $owner->password)) {
            throw new InvalidArgumentException('Incorrect password.');
        }

        $owner->mfa_secret = null;
        $owner->mfa_enabled_at = null;
        $owner->save();

        BusinessOwnerRecoveryCode::query()->where('business_owner_id', $ownerId)->delete();
    }
}
