<?php

namespace App\Domains\Nexus\Sso\Domain\Services;

use App\Domains\Nexus\Sso\Domain\ValueObjects\SsoIdentity;

/**
 * An outbound port to an external identity provider — same "Domain/Services
 * for a port to an external system" placement LLMProviderInterface already
 * established (Phase 4/M1), not Domain/Repositories (this isn't persistence).
 * `redirectUrl()` returns a plain string rather than a Response — the
 * controller decides how to redirect, keeping this interface transport-shape
 * agnostic. `handleCallback()` takes no Request parameter: every real
 * implementation reads the ambient HTTP request itself (Socialite's own
 * facade does this internally for OAuth), so nothing here forces a
 * framework type into the signature.
 */
interface SsoProviderInterface
{
    public function key(): string;

    /**
     * False for a stub (SAML/LDAP, Phase 7/M8) that's registered so admins
     * can see it exists, but isn't wired to a real Identity Provider yet.
     */
    public function supportsInteractiveLogin(): bool;

    public function redirectUrl(): string;

    public function handleCallback(): SsoIdentity;

    /**
     * Whether this provider has real, usable configuration behind it —
     * distinct from `supportsInteractiveLogin()`: a stub can be "not
     * configured" AND "doesn't support interactive login" at once, but the
     * two questions are independent (a provider could in principle be
     * fully configured yet still non-interactive, e.g. a future
     * backend-only directory sync). NexusSsoProvidersController surfaces
     * both.
     */
    public function isConfigured(): bool;
}
