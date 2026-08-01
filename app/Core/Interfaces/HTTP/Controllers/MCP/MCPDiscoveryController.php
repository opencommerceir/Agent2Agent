<?php

namespace App\Core\Interfaces\HTTP\Controllers\MCP;

use App\Core\Application\Actions\DiscoverCapabilitiesAction;
use App\Core\Application\Services\AgentAuthenticationService;
use App\Core\Application\Services\MCPResponseFormatter;
use Illuminate\Http\JsonResponse;

/**
 * GET /mcp/v1/capabilities — lets an Agent discover every capability that
 * exists on the platform. Still requires a valid Agent token: every MCP
 * request must be authenticated, discovery included — it just doesn't
 * filter the list by what the calling Agent is individually permitted to
 * do (see DiscoverCapabilitiesAction docblock).
 *
 * The Authenticate -> list sequence now lives on
 * AbstractMCPDiscoveryController (Stage 7, API Versioning); this class
 * owns only v1's own response envelope, {"data": {"capabilities": [...]},
 * "meta": {"count": N}} — unchanged from before Stage 7.
 *
 * No try/catch: an invalid/missing token throws, and MCPExceptionHandler
 * (bootstrap/app.php) turns that into the 401 UNAUTHORIZED envelope.
 */
final class MCPDiscoveryController extends AbstractMCPDiscoveryController
{
    public function __construct(
        AgentAuthenticationService $agentAuthentication,
        DiscoverCapabilitiesAction $discoverCapabilities,
        private readonly MCPResponseFormatter $response,
    ) {
        parent::__construct($agentAuthentication, $discoverCapabilities);
    }

    protected function formatResponse(array $capabilities): JsonResponse
    {
        return $this->response->success(
            ['capabilities' => $capabilities],
            ['count' => count($capabilities)],
        );
    }
}
