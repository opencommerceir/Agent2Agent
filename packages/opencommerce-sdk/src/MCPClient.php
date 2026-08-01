<?php

namespace OpenCommerce\SDK;

use OpenCommerce\SDK\Authentication\AuthenticatedRequest;
use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\Discovery\CapabilityDiscovery;
use OpenCommerce\SDK\DTOs\Capability;
use OpenCommerce\SDK\DTOs\CapabilityInput;
use OpenCommerce\SDK\Exceptions\NotFoundException;
use OpenCommerce\SDK\Execution\CapabilityExecutor;
use OpenCommerce\SDK\Execution\ExecutionResult;

/**
 * The one class an Agent developer needs to know about.
 *
 * ```php
 * $config = new MCPConfig(baseUrl: 'https://api.opencommerce.ir/mcp/v1', token: 'agent_token');
 * // or, equivalently: MCPConfig::forVersion(host: 'https://api.opencommerce.ir', version: 'v1', token: 'agent_token');
 * $client = new MCPClient($config);
 *
 * $capabilities = $client->discoverCapabilities();
 * $result = $client->execute('commerce.product.search', ['query' => 'laptop']);
 * ```
 *
 * Migrating to v2 (docs/api/migration/v1-to-v2.md) is a one-argument
 * change: construct $config with version: 'v2' instead of 'v1' — nothing
 * else about this class's own API changes, since v1/v2 only differ in the
 * server's response envelope shape, and ExecutionResult already normalizes
 * that (Discovery/CapabilityExecutor's own docblocks).
 *
 * Takes an MCPConfig object rather than flat constructor arguments
 * (baseUrl, token, ...) — a deliberate change from this SDK's first draft
 * inside the main app (App\SDK\MCPClient): a config object scales better
 * as more connection options get added later without the constructor
 * signature growing indefinitely.
 */
final class MCPClient
{
    private readonly CapabilityDiscovery $discovery;

    private readonly CapabilityExecutor $executor;

    public function __construct(MCPConfig $config)
    {
        $request = new AuthenticatedRequest($config);

        $this->discovery = new CapabilityDiscovery($request);
        $this->executor = new CapabilityExecutor($request);
    }

    /**
     * @return list<Capability>
     */
    public function discoverCapabilities(): array
    {
        return $this->discovery->discover();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(string $capabilityName, array $input = []): ExecutionResult
    {
        return $this->executor->execute($capabilityName, CapabilityInput::fromArray($input));
    }

    /**
     * There is no GET /mcp/v1/capabilities/{name} endpoint on the server
     * today — this fetches the full discovery list and filters client-side.
     */
    public function getCapability(string $capabilityName): Capability
    {
        foreach ($this->discoverCapabilities() as $capability) {
            if ($capability->name === $capabilityName) {
                return $capability;
            }
        }

        throw new NotFoundException('NOT_FOUND', "Capability [{$capabilityName}] was not found.", 404);
    }
}
