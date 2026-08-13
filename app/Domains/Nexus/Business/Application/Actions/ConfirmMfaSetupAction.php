<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Domain\Services\TotpService;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwnerRecoveryCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Step 2 of 2 — the real code from the just-scanned authenticator app is
 * the only thing that flips `mfa_enabled_at`. Returns 10 one-time recovery
 * codes in plaintext exactly once (the caller's view must show them
 * immediately; nothing else in this codebase ever reads them back
 * unhashed) — same "shown once, stored hashed" shape as
 * InviteTeamMemberAction's temporary password, one tier more careful since
 * these bypass MFA entirely if leaked.
 */
final class ConfirmMfaSetupAction
{
    private const RECOVERY_CODE_COUNT = 10;

    public function __construct(
        private readonly TotpService $totp,
    ) {
    }

    /**
     * @return list<string>
     */
    public function execute(int $ownerId, string $code): array
    {
        $owner = BusinessOwner::query()->find($ownerId);

        if (! $owner || ! $owner->mfa_secret) {
            throw new InvalidArgumentException('MFA setup has not been started for this owner.');
        }

        if (! $this->totp->verify($owner->mfa_secret, $code)) {
            throw new InvalidArgumentException('The provided code is invalid or expired.');
        }

        $owner->mfa_enabled_at = now();
        $owner->save();

        BusinessOwnerRecoveryCode::query()->where('business_owner_id', $ownerId)->delete();

        $plainCodes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $plainCode = strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            $plainCodes[] = $plainCode;

            BusinessOwnerRecoveryCode::query()->create([
                'business_owner_id' => $ownerId,
                'code_hash' => Hash::make($plainCode),
                'created_at' => now(),
            ]);
        }

        return $plainCodes;
    }
}
