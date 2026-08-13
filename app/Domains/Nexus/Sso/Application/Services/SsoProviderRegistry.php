<?php

namespace App\Domains\Nexus\Sso\Application\Services;

use App\Domains\Nexus\Sso\Domain\Exceptions\SsoProviderNotFoundException;
use App\Domains\Nexus\Sso\Domain\Services\SsoProviderInterface;

/**
 * The sixth application of this codebase's Connector pattern (after
 * ConnectorRegistry/ShippingProviderRegistry/ChannelSenderRegistry/
 * PaymentGatewayRegistry/LLMProviderRegistry) — same in-memory
 * register()/get()/registered() shape as PaymentGatewayRegistry, built here
 * once; Phase 7/M8 only adds two more register() calls to this same
 * instance, it does not build a second registry.
 */
final class SsoProviderRegistry
{
    /**
     * @var array<string, SsoProviderInterface>
     */
    private array $providers = [];

    public function register(string $key, SsoProviderInterface $provider): void
    {
        $this->providers[$key] = $provider;
    }

    public function get(string $key): SsoProviderInterface
    {
        if (! isset($this->providers[$key])) {
            throw new SsoProviderNotFoundException("No SSO provider registered under [{$key}].");
        }

        return $this->providers[$key];
    }

    /**
     * @return list<SsoProviderInterface>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }

    /**
     * @return list<string>
     */
    public function registered(): array
    {
        return array_keys($this->providers);
    }
}
