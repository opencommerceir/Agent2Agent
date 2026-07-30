<?php

namespace App\SDK\Discovery;

use App\SDK\Authentication\AuthenticatedRequest;
use App\SDK\DTOs\Capability;
use App\SDK\Exceptions\MCPException;

/**
 * Calls GET /mcp/v1/capabilities and turns the response into typed
 * Capability DTOs. No caching — deliberately skipped as the optional
 * feature it was described as, to keep the SDK's behavior predictable
 * (a cached list could go stale the moment a new capability is
 * registered); add a cache wrapper around MCPClient if a consumer wants
 * that trade-off, rather than baking staleness into the SDK itself.
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
        $response = $this->request->client()->get('capabilities');

        if ($response->failed()) {
            throw MCPException::fromResponse($response->status(), $response->json() ?? []);
        }

        $capabilities = $response->json('data.capabilities', []);

        return array_map(fn (array $capability) => Capability::fromArray($capability), $capabilities);
    }
}
