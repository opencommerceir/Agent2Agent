<?php

namespace Tests\Feature\Nexus\Sso;

use App\Domains\Nexus\Sso\Application\Services\SsoProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Feature test (not Unit) because it needs the real app container —
 * NexusServiceProvider::boot() is what actually registers all three
 * providers onto the shared singleton.
 */
class SsoProviderRegistryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_hasAllThreeRegisteredKeys(): void
    {
        $registry = app(SsoProviderRegistry::class);

        $this->assertSame(['google', 'saml', 'ldap'], $registry->registered());
    }

    public function test_googleProvider_supportsInteractiveLogin(): void
    {
        $registry = app(SsoProviderRegistry::class);

        $this->assertTrue($registry->get('google')->supportsInteractiveLogin());
    }

    public function test_samlAndLdapProviders_doNotSupportInteractiveLogin(): void
    {
        $registry = app(SsoProviderRegistry::class);

        $this->assertFalse($registry->get('saml')->supportsInteractiveLogin());
        $this->assertFalse($registry->get('ldap')->supportsInteractiveLogin());
    }

    public function test_get_forUnregisteredKey_throws(): void
    {
        $registry = app(SsoProviderRegistry::class);

        $this->expectException(\App\Domains\Nexus\Sso\Domain\Exceptions\SsoProviderNotFoundException::class);

        $registry->get('okta');
    }
}
