<?php

namespace App\SDK;

use App\SDK\Authentication\AuthenticatedRequest;
use App\SDK\Config\MCPConfig;
use App\SDK\Discovery\CapabilityDiscovery;
use App\SDK\DTOs\Capability;
use App\SDK\DTOs\CapabilityInput;
use App\SDK\Exceptions\NotFoundException;
use App\SDK\Execution\CapabilityExecutor;
use App\SDK\Execution\ExecutionResult;

/**
 * The one class an Agent developer needs to know about. Everything else
 * in App\SDK is an implementation detail wired together here.
 *
 * ```php
 * $client = new MCPClient(baseUrl: 'https://api.opencommerce.ir/mcp/v1', token: 'agent_token');
 * $capabilities = $client->discoverCapabilities();
 * $result = $client->execute('commerce.product.search', ['query' => 'laptop']);
 * ```
 *
 * Constructed directly with `new` rather than resolved from Laravel's
 * container — an Agent integration is typically its own small
 * application, not necessarily this one, so the SDK doesn't assume a
 * service container is even available.
 */
final class MCPClient
{
    private readonly CapabilityDiscovery $discovery;

    private readonly CapabilityExecutor $executor;

    public function __construct(string $baseUrl, string $token, int $timeout = 30, bool $verifySSL = true)
    {
        $request = new AuthenticatedRequest(new MCPConfig($baseUrl, $token, $timeout, $verifySSL));

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
     * Fine while capability counts are small; would need a dedicated
     * server endpoint to stay cheap if that list grows large.
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
