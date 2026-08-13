<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Domain\Services\TotpService;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwnerRecoveryCode;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Tried in order: a real TOTP code first (the common case), then a
 * recovery code (deleted immediately on match — single-use). Deliberately
 * does not distinguish "wrong TOTP" from "wrong recovery code" in its
 * failure — a single generic failure keeps this challenge from leaking
 * which format the caller was attempting.
 */
final class VerifyMfaChallengeAction
{
    public function __construct(
        private readonly TotpService $totp,
    ) {
    }

    public function execute(int $ownerId, string $code): bool
    {
        $owner = BusinessOwner::query()->find($ownerId);

        if (! $owner || ! $owner->mfa_secret || ! $owner->mfa_enabled_at) {
            throw new InvalidArgumentException("Business owner [{$ownerId}] does not have MFA enabled.");
        }

        if ($this->totp->verify($owner->mfa_secret, $code)) {
            return true;
        }

        return $this->consumeRecoveryCodeIfValid($ownerId, $code);
    }

    private function consumeRecoveryCodeIfValid(int $ownerId, string $code): bool
    {
        $candidates = BusinessOwnerRecoveryCode::query()
            ->where('business_owner_id', $ownerId)
            ->whereNull('used_at')
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($code, $candidate->code_hash)) {
                $candidate->delete();

                return true;
            }
        }

        return false;
    }
}
