<?php

namespace OpenCommerce\SDK\Laravel\Tests;

use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\Laravel\Facades\OpenCommerce;
use OpenCommerce\SDK\Laravel\OpenCommerceServiceProvider;
use OpenCommerce\SDK\MCPClient;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use ReflectionMethod;

final class OpenCommerceServiceProviderTest extends TestCase
{
    public function test_defaultConfig_isMergedFromThePackagesOwnFile(): void
    {
        $this->assertSame('v1', config('opencommerce.version'));
        $this->assertSame('https://api.opencommerce.ir', config('opencommerce.host'));
        $this->assertSame(30, config('opencommerce.timeout'));
        $this->assertTrue(config('opencommerce.verify_ssl'));
    }

    public function test_mcpClient_resolvesAsARealSingletonFromTheContainer(): void
    {
        $first = $this->app->make(MCPClient::class);
        $second = $this->app->make(MCPClient::class);

        $this->assertInstanceOf(MCPClient::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_opencommerceAlias_resolvesTheSameSingleton(): void
    {
        $viaClass = $this->app->make(MCPClient::class);
        $viaAlias = $this->app->make('opencommerce');

        $this->assertSame($viaClass, $viaAlias);
    }

    public function test_facade_resolvesTheSameSingletonBoundInTheContainer(): void
    {
        $this->assertSame(
            $this->app->make(MCPClient::class),
            OpenCommerce::getFacadeRoot(),
        );
    }

    public function test_resolveConfig_withExplicitBaseUrl_usesItDirectlyAndIgnoresHostVersion(): void
    {
        $config = $this->resolveConfig([
            'base_url' => 'https://custom.example.com/mcp/v2',
            'host' => 'https://ignored.example.com',
            'version' => 'v1',
            'token' => 'agent-token',
            'timeout' => 45,
            'verify_ssl' => false,
        ]);

        $this->assertSame('https://custom.example.com/mcp/v2', $config->baseUrl);
        $this->assertSame('agent-token', $config->token);
        $this->assertSame(45, $config->timeout);
        $this->assertFalse($config->verifySSL);
    }

    public function test_resolveConfig_withNoBaseUrl_buildsItFromHostAndVersion(): void
    {
        $config = $this->resolveConfig([
            'host' => 'https://api.opencommerce.ir',
            'version' => 'v2',
            'token' => 'agent-token',
        ]);

        $this->assertSame('https://api.opencommerce.ir/mcp/v2', $config->baseUrl);
    }

    public function test_resolveConfig_defaultTimeoutAndVerifySsl_matchMCPConfigsOwnDefaults(): void
    {
        $config = $this->resolveConfig(['host' => 'https://api.opencommerce.ir', 'token' => 't']);

        $this->assertSame(30, $config->timeout);
        $this->assertTrue($config->verifySSL);
    }

    public function test_configFile_isRegisteredForPublishingUnderTheExpectedTag(): void
    {
        // Illuminate\Support\ServiceProvider::pathsToPublish() reads back
        // the static registry boot() already populated for this provider —
        // the same lookup Laravel's own vendor:publish command performs.
        $paths = LaravelServiceProvider::pathsToPublish(OpenCommerceServiceProvider::class, 'opencommerce-config');

        $this->assertNotEmpty($paths);
        $this->assertStringEndsWith('opencommerce.php', str_replace('\\', '/', array_key_first($paths)));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveConfig(array $config): MCPConfig
    {
        $provider = new OpenCommerceServiceProvider($this->app);
        $method = new ReflectionMethod($provider, 'resolveConfig');
        $method->setAccessible(true);

        /** @var MCPConfig $result */
        $result = $method->invoke($provider, $config);

        return $result;
    }
}
