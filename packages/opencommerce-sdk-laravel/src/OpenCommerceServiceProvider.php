<?php

namespace OpenCommerce\SDK\Laravel;

use Illuminate\Support\ServiceProvider;
use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\MCPClient;

/**
 * Auto-resolves a configured MCPClient (packages/opencommerce-sdk) from
 * config/opencommerce.php — the one thing the framework-agnostic PHP SDK
 * deliberately doesn't do itself (it only ever takes an explicit MCPConfig
 * object, on purpose, so it stays usable outside Laravel too).
 *
 * Registered as a real singleton (not a closure re-evaluated per
 * resolution, unlike several config-driven bindings in the main
 * OpenCommerce application itself) — a consuming app's own
 * config/opencommerce.php doesn't change mid-request the way a test might
 * flip AgentOrchestrator's own planner.type, so there's no rebind-in-a-test
 * requirement to protect here.
 */
final class OpenCommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/opencommerce.php', 'opencommerce');

        $this->app->singleton(MCPClient::class, function ($app): MCPClient {
            return new MCPClient($this->resolveConfig($app['config']->get('opencommerce', [])));
        });

        $this->app->alias(MCPClient::class, 'opencommerce');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/opencommerce.php' => $this->app->configPath('opencommerce.php'),
            ], 'opencommerce-config');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveConfig(array $config): MCPConfig
    {
        $token = (string) ($config['token'] ?? '');
        $timeout = (int) ($config['timeout'] ?? 30);
        $verifySsl = (bool) ($config['verify_ssl'] ?? true);

        $baseUrl = $config['base_url'] ?? null;

        if (is_string($baseUrl) && $baseUrl !== '') {
            return new MCPConfig(
                baseUrl: $baseUrl,
                token: $token,
                timeout: $timeout,
                verifySSL: $verifySsl,
            );
        }

        return MCPConfig::forVersion(
            host: (string) ($config['host'] ?? ''),
            version: (string) ($config['version'] ?? 'v1'),
            token: $token,
            timeout: $timeout,
            verifySSL: $verifySsl,
        );
    }
}
