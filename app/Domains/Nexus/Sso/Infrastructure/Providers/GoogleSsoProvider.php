<?php

namespace App\Domains\Nexus\Sso\Infrastructure\Providers;

use App\Domains\Nexus\Sso\Domain\Services\SsoProviderInterface;
use App\Domains\Nexus\Sso\Domain\ValueObjects\SsoIdentity;
use Laravel\Socialite\Facades\Socialite;

/**
 * The one real, live SSO adapter this phase wires end-to-end — same "prove
 * the connector with one real implementation first" restraint Phase 3/M3
 * used for Zibal-only (Stripe registered but not reachable from
 * PurchaseCreditsAction yet). Microsoft/other OAuth providers are a
 * config-only follow-up once wanted (would need the community
 * socialiteprovders/microsoft package on top of core Socialite, deferred).
 *
 * Stateful (session-based) Socialite, not ->stateless() — this is a normal
 * browser login flow with Laravel's own session middleware already active,
 * so CSRF-style `state` verification across redirect/callback is free and
 * should not be turned off.
 */
final class GoogleSsoProvider implements SsoProviderInterface
{
    public function key(): string
    {
        return 'google';
    }

    public function supportsInteractiveLogin(): bool
    {
        return true;
    }

    public function redirectUrl(): string
    {
        return Socialite::driver('google')->redirect()->getTargetUrl();
    }

    public function handleCallback(): SsoIdentity
    {
        $googleUser = Socialite::driver('google')->user();

        return new SsoIdentity(
            providerKey: $this->key(),
            providerUserId: (string) $googleUser->getId(),
            email: $googleUser->getEmail(),
            name: $googleUser->getName() ?? $googleUser->getNickname() ?? $googleUser->getEmail(),
        );
    }
}
