<?php

namespace Tests\Unit\Nexus\Sso;

use App\Domains\Nexus\Sso\Domain\Exceptions\SsoProviderNotConfiguredException;
use App\Domains\Nexus\Sso\Infrastructure\Providers\LdapSsoProvider;
use App\Domains\Nexus\Sso\Infrastructure\Providers\SamlSsoProvider;
use PHPUnit\Framework\TestCase;

class SsoStubProvidersTest extends TestCase
{
    public function test_samlProvider_isNotInteractiveAndUnconfiguredByDefault(): void
    {
        $provider = new SamlSsoProvider(null, null, null);

        $this->assertSame('saml', $provider->key());
        $this->assertFalse($provider->supportsInteractiveLogin());
        $this->assertFalse($provider->isConfigured());
    }

    public function test_samlProvider_redirectUrl_throwsNotConfigured(): void
    {
        $provider = new SamlSsoProvider(null, null, null);

        $this->expectException(SsoProviderNotConfiguredException::class);

        $provider->redirectUrl();
    }

    public function test_samlProvider_handleCallback_throwsNotConfigured(): void
    {
        $provider = new SamlSsoProvider('entity', 'https://idp.example.com/sso', 'cert');

        $this->expectException(SsoProviderNotConfiguredException::class);

        $provider->handleCallback();
    }

    public function test_samlProvider_withFullConfig_reportsConfiguredButStillNonInteractive(): void
    {
        $provider = new SamlSsoProvider('entity', 'https://idp.example.com/sso', 'cert');

        $this->assertTrue($provider->isConfigured());
        $this->assertFalse($provider->supportsInteractiveLogin());
    }

    public function test_ldapProvider_isNotInteractiveAndUnconfiguredByDefault(): void
    {
        $provider = new LdapSsoProvider(null, null);

        $this->assertSame('ldap', $provider->key());
        $this->assertFalse($provider->supportsInteractiveLogin());
        $this->assertFalse($provider->isConfigured());
    }

    public function test_ldapProvider_redirectUrl_throwsNotConfigured(): void
    {
        $provider = new LdapSsoProvider(null, null);

        $this->expectException(SsoProviderNotConfiguredException::class);

        $provider->redirectUrl();
    }

    public function test_ldapProvider_handleCallback_throwsNotConfigured(): void
    {
        $provider = new LdapSsoProvider('ldap.example.com', 'dc=example,dc=com');

        $this->expectException(SsoProviderNotConfiguredException::class);

        $provider->handleCallback();
    }
}
