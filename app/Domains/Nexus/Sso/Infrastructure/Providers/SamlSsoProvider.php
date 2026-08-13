<?php

namespace App\Domains\Nexus\Sso\Infrastructure\Providers;

use App\Domains\Nexus\Sso\Domain\Exceptions\SsoProviderNotConfiguredException;
use App\Domains\Nexus\Sso\Domain\Services\SsoProviderInterface;
use App\Domains\Nexus\Sso\Domain\ValueObjects\SsoIdentity;

/**
 * A real class, registered into the same SsoProviderRegistry GoogleSsoProvider
 * uses (Phase 7/M6) — but honestly stubbed: no SAML library
 * (e.g. `slides/saml2`) is installed, and there is no real enterprise
 * Identity Provider reachable in this environment to test a SAML exchange
 * against. Reads real config keys (`nexus.platform.sso.saml.*`) so an admin
 * configuring an IdP later has a real, discoverable place to put entity
 * ID/certificate/SSO URL — wiring the actual assertion-consumption logic
 * once a real package/IdP is in scope is the natural next step, not a
 * silent gap.
 */
final class SamlSsoProvider implements SsoProviderInterface
{
    public function __construct(
        private readonly ?string $entityId,
        private readonly ?string $ssoUrl,
        private readonly ?string $certificate,
    ) {
    }

    public function key(): string
    {
        return 'saml';
    }

    public function supportsInteractiveLogin(): bool
    {
        return false;
    }

    public function redirectUrl(): string
    {
        throw new SsoProviderNotConfiguredException(
            'SAML SSO is registered but not connected to a real Identity Provider — set nexus.platform.sso.saml.* and implement the real assertion exchange to enable it.'
        );
    }

    public function handleCallback(): SsoIdentity
    {
        throw new SsoProviderNotConfiguredException(
            'SAML SSO is registered but not connected to a real Identity Provider — set nexus.platform.sso.saml.* and implement the real assertion exchange to enable it.'
        );
    }

    public function isConfigured(): bool
    {
        return $this->entityId !== null && $this->ssoUrl !== null && $this->certificate !== null;
    }
}
