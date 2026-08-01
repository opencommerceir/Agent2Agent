<?php

namespace OpenCommerce\SDK\Discovery;

use OpenCommerce\SDK\Authentication\AuthenticatedRequest;
use OpenCommerce\SDK\DTOs\Capability;
use OpenCommerce\SDK\Exceptions\MCPException;

/**
 * Calls GET /{version}/capabilities (MCPConfig::$baseUrl already carries
 * the version segment) and turns the response into typed Capability DTOs.
 * No caching — a cached list could go stale the moment a new capability
 * is registered; wrap MCPClient yourself if you want that trade-off.
 *
 * v1 nests `capabilities` under `data`; v2 (docs/api/v2/changes.md) puts
 * it at the top level next to `metadata`. Checks both, the same
 * either-envelope-shape tolerance CapabilityExecutor applies to
 * `result`/`data` and `metadata`/`meta`.
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

        $capabilities = $response['body']['data']['capabilities']
            ?? $response['body']['capabilities']
            ?? [];

        return array_map(fn (array $capability) => Capability::fromArray($capability), $capabilities);
    }
}
