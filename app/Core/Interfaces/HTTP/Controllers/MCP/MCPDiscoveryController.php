<?php

namespace App\Core\Interfaces\HTTP\Controllers\MCP;

use App\Core\Application\Actions\DiscoverCapabilitiesAction;
use App\Core\Application\DTOs\CapabilityData;
use App\Core\Application\Services\AgentAuthenticationService;
use App\Core\Application\Services\MCPResponseFormatter;
use App\Core\Domain\Exceptions\AgentNotActiveException;
use App\Core\Domain\Exceptions\InvalidAgentTokenException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /mcp/v1/capabilities — lets an Agent discover every capability that
 * exists on the platform (Capability Registry as discovery layer,
 * architecture.md). Still requires a valid Agent token: every MCP request
 * must be authenticated, discovery included — it just doesn't filter the
 * list by what the calling Agent is individually permitted to do (see
 * DiscoverCapabilitiesAction docblock).
 */
final class MCPDiscoveryController extends Controller
{
    public function __construct(
        private readonly AgentAuthenticationService $agentAuthentication,
        private readonly DiscoverCapabilitiesAction $discoverCapabilities,
        private readonly MCPResponseFormatter $response,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $this->agentAuthentication->authenticateFromRequest($request);
        } catch (InvalidAgentTokenException|AgentNotActiveException $e) {
            return $this->response->error('UNAUTHORIZED', $e->getMessage(), 401);
        }

        $capabilities = array_map(
            fn (CapabilityData $capability) => $capability->toArray(),
            $this->discoverCapabilities->execute(),
        );

        return $this->response->success(
            ['capabilities' => $capabilities],
            ['count' => count($capabilities)],
        );
    }
}
