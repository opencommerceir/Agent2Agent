<?php

namespace App\Core\Interfaces\HTTP\Controllers\MCP;

use App\Core\Application\Actions\DiscoverCapabilitiesAction;
use App\Core\Application\DTOs\CapabilityData;
use App\Core\Application\Services\AgentAuthenticationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /mcp/v{N}/capabilities — the Authenticate -> list -> format sequence,
 * identical across every wire version (Stage 7, API Versioning, the same
 * "shared base class, version-specific formatResponse()" split
 * AbstractMCPGatewayController establishes for POST /execute).
 */
abstract class AbstractMCPDiscoveryController extends Controller
{
    public function __construct(
        protected readonly AgentAuthenticationService $agentAuthentication,
        protected readonly DiscoverCapabilitiesAction $discoverCapabilities,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $agent = $this->agentAuthentication->authenticateFromRequest($request);
        $request->attributes->set('agent_id', $agent->id);

        $capabilities = array_map(
            fn (CapabilityData $capability) => $capability->toArray(),
            $this->discoverCapabilities->execute(),
        );

        return $this->formatResponse($capabilities);
    }

    /**
     * @param array<int, array<string, mixed>> $capabilities
     */
    abstract protected function formatResponse(array $capabilities): JsonResponse;
}
