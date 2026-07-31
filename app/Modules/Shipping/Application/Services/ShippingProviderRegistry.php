<?php

namespace App\Modules\Shipping\Application\Services;

use App\Modules\Shipping\Domain\Exceptions\ShippingProviderNotFoundException;
use App\Modules\Shipping\Domain\Services\ShippingProviderInterface;

/**
 * In-memory lookup of "which provider handles which name", keyed by
 * provider name (e.g. 'mock', 'usps'). Registered once in
 * ShippingServiceProvider::boot() — mirrors Commerce's own
 * `ConnectorRegistry` exactly (same seed-of-a-future-Connection-Manager
 * reasoning that class's own docblock gives), placed in Application
 * layer for the identical reason: a plain lookup, no domain rule to
 * protect.
 */
final class ShippingProviderRegistry
{
    /**
     * @var array<string, ShippingProviderInterface>
     */
    private array $providers = [];

    public function register(string $name, ShippingProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    public function get(string $name): ShippingProviderInterface
    {
        if (! isset($this->providers[$name])) {
            throw new ShippingProviderNotFoundException("No shipping provider registered under [{$name}].");
        }

        return $this->providers[$name];
    }

    /**
     * @return list<string>
     */
    public function registered(): array
    {
        return array_keys($this->providers);
    }
}
