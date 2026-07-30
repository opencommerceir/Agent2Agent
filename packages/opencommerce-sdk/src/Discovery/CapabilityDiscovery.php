<?php

namespace OpenCommerce\SDK\Discovery;

use OpenCommerce\SDK\Authentication\AuthenticatedRequest;
use OpenCommerce\SDK\DTOs\Capability;
use OpenCommerce\SDK\Exceptions\MCPException;

/**
 * Calls GET /mcp/v1/capabilities and turns the response into typed
 * Capability DTOs. No caching — a cached list could go stale the moment a
 * new capability is registered; wrap MCPClient yourself if you want that
 * trade-off.
 */
final class CapabilityDiscovery
{
    public function __construct(
        private readonly AuthenticatedRequest $request,
    ) {
    }

    /**
     * @return list<Capability>
     */
    public function discover(): array
    {
        $response = $this->request->get('capabilities');

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw MCPException::fromResponse($response['status'], $response['body']);
        }

        $capabilities = $response['body']['data']['capabilities'] ?? [];

        return array_map(fn (array $capability) => Capability::fromArray($capability), $capabilities);
    }
}
