<?php

namespace App\Domains\Nexus\Llm\Application\Services;

use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderNotFoundException;
use App\Domains\Nexus\Llm\Domain\Services\LLMProviderInterface;

/**
 * In-memory lookup of "which provider handles which registry key" (e.g.
 * 'openai', 'qwen-14b-local') — the fifth application of the Connector
 * Pattern in this codebase (`ConnectorRegistry`/`ShippingProviderRegistry`/
 * `ChannelSenderRegistry`/`PaymentGatewayRegistry`), mirroring
 * `App\Modules\Commerce\Application\Services\PaymentGatewayRegistry`
 * exactly. Registered as a singleton and populated once in
 * `NexusServiceProvider::boot()`. Adding a new provider means implementing
 * `LLMProviderInterface` and calling `register()` here under a new
 * key — no other file in this class needs to change.
 */
final class LLMProviderRegistry
{
    /**
     * @var array<string, LLMProviderInterface>
     */
    private array $providers = [];

    public function register(string $name, LLMProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    public function get(string $name): LLMProviderInterface
    {
        if (! isset($this->providers[$name])) {
            throw new LLMProviderNotFoundException("No LLM provider registered under [{$name}].");
        }

        return $this->providers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * @return list<string>
     */
    public function registered(): array
    {
        return array_keys($this->providers);
    }
}
