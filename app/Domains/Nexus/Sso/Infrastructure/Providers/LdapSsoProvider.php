<?php

namespace App\Domains\Nexus\Sso\Infrastructure\Providers;

use App\Domains\Nexus\Sso\Domain\Exceptions\SsoProviderNotConfiguredException;
use App\Domains\Nexus\Sso\Domain\Services\SsoProviderInterface;
use App\Domains\Nexus\Sso\Domain\ValueObjects\SsoIdentity;

/**
 * Same honest-stub tier as SamlSsoProvider — no LDAP library
 * (e.g. `directorytree/ldaprecord`) installed, no real directory server
 * reachable here. LDAP has no browser redirect step at all (it's a direct
 * bind against a directory server, not OAuth/SAML), so `redirectUrl()`
 * throws unconditionally rather than pretending one exists;
 * `supportsInteractiveLogin()` is false for the same reason
 * SamlSsoProvider's is — the admin surface (NexusSsoProvidersController)
 * is what actually tells an operator "this one isn't live yet."
 */
final class LdapSsoProvider implements SsoProviderInterface
{
    public function __construct(
        private readonly ?string $host,
        private readonly ?string $baseDn,
    ) {
    }

    public function key(): string
    {
        return 'ldap';
    }

    public function supportsInteractiveLogin(): bool
    {
        return false;
    }

    public function redirectUrl(): string
    {
        throw new SsoProviderNotConfiguredException(
            'LDAP SSO is registered but not connected to a real directory server — set nexus.platform.sso.ldap.* and implement the real bind/search exchange to enable it.'
        );
    }

    public function handleCallback(): SsoIdentity
    {
        throw new SsoProviderNotConfiguredException(
            'LDAP SSO is registered but not connected to a real directory server — set nexus.platform.sso.ldap.* and implement the real bind/search exchange to enable it.'
        );
    }

    public function isConfigured(): bool
    {
        return $this->host !== null && $this->baseDn !== null;
    }
}
