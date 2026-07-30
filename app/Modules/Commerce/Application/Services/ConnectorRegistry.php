<?php

namespace App\Modules\Commerce\Application\Services;

use App\Modules\Commerce\Domain\Connectors\OrderConnectorInterface;
use App\Modules\Commerce\Domain\Connectors\ProductConnectorInterface;
use InvalidArgumentException;

/**
 * In-memory lookup of "which connector handles which external system",
 * keyed by connector name (e.g. 'woocommerce', 'mock'). Registered once
 * in CommerceServiceProvider::boot().
 *
 * This is the seed of what architecture.md calls the Connection Manager,
 * not the full thing — a real Connection Manager also owns credential
 * storage, per-tenant connection configuration, and health-monitoring
 * scheduling, none of which exist yet. Kept Commerce-scoped for now since
 * Commerce is the only domain module that exists; if CRM/ERP later need
 * their own connector sets, whether this becomes a shared Core service is
 * a decision for that point, not one to pre-empt today.
 */
final class ConnectorRegistry
{
    /**
     * @var array<string, ProductConnectorInterface>
     */
    private array $productConnectors = [];

    /**
     * @var array<string, OrderConnectorInterface>
     */
    private array $orderConnectors = [];

    public function registerProductConnector(string $name, ProductConnectorInterface $connector): void
    {
        $this->productConnectors[$name] = $connector;
    }

    public function getProductConnector(string $name): ProductConnectorInterface
    {
        if (! isset($this->productConnectors[$name])) {
            throw new InvalidArgumentException("No product connector registered under [{$name}].");
        }

        return $this->productConnectors[$name];
    }

    /**
     * @return list<string>
     */
    public function registeredProductConnectors(): array
    {
        return array_keys($this->productConnectors);
    }

    public function registerOrderConnector(string $name, OrderConnectorInterface $connector): void
    {
        $this->orderConnectors[$name] = $connector;
    }

    public function getOrderConnector(string $name): OrderConnectorInterface
    {
        if (! isset($this->orderConnectors[$name])) {
            throw new InvalidArgumentException("No order connector registered under [{$name}].");
        }

        return $this->orderConnectors[$name];
    }

    /**
     * @return list<string>
     */
    public function registeredOrderConnectors(): array
    {
        return array_keys($this->orderConnectors);
    }
}
