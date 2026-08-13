<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwnerOauthIdentity;
use InvalidArgumentException;

/**
 * Only ever called after BusinessOauthController's own password-confirmation
 * step verifies the caller really controls the existing account — a
 * lightweight "explicit confirmation" (Phase 7 plan's own wording),
 * substantive enough that a bare "click confirm" button never suffices:
 * proving the OAuth email happens to match isn't proof of ownership,
 * re-entering that account's own password is.
 */
final class LinkOauthIdentityToOwnerAction
{
    public function execute(int $businessOwnerId, string $provider, string $providerUserId): void
    {
        if (BusinessOwnerOauthIdentity::query()->where('provider', $provider)->where('provider_user_id', $providerUserId)->exists()) {
            throw new InvalidArgumentException("This {$provider} account is already linked to a Business owner.");
        }

        BusinessOwnerOauthIdentity::query()->create([
            'business_owner_id' => $businessOwnerId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'created_at' => now(),
        ]);
    }
}
