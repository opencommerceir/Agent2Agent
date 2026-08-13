<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Application\DTOs\MfaSetupData;
use App\Domains\Nexus\Business\Domain\Services\TotpService;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use InvalidArgumentException;

/**
 * Step 1 of 2 — generates and stores a secret but does NOT set
 * `mfa_enabled_at` yet; ConfirmMfaSetupAction only flips that after a real
 * code from the authenticator app proves the owner actually captured the
 * secret correctly. Re-running this before confirming just issues a fresh
 * secret (the old, never-confirmed one was never trusted anyway).
 */
final class EnableMfaAction
{
    public function __construct(
        private readonly TotpService $totp,
    ) {
    }

    public function execute(int $ownerId): MfaSetupData
    {
        $owner = BusinessOwner::query()->find($ownerId);

        if (! $owner) {
            throw new InvalidArgumentException("Business owner [{$ownerId}] does not exist.");
        }

        if ($owner->mfa_enabled_at !== null) {
            throw new InvalidArgumentException('MFA is already enabled for this owner.');
        }

        $secret = $this->totp->generateSecret();
        $owner->mfa_secret = $secret;
        $owner->save();

        return new MfaSetupData($secret, $this->totp->otpauthUri($secret, $owner->email));
    }
}
