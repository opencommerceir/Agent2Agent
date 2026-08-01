<?php

namespace App\Core\Interfaces\HTTP\Controllers\MCP;

use Illuminate\Http\JsonResponse;

/**
 * GET /mcp/v2/capabilities — same Authenticate -> list sequence as v1
 * (AbstractMCPDiscoveryController), the v2 envelope convention applied
 * consistently: {"capabilities": [...], "metadata": {"api_version": "v2",
 * "count": N, "timestamp": ...}} — the same `metadata`-wrapping,
 * `api_version`/`timestamp`-adding shape MCPGatewayControllerV2 uses for
 * /execute. Not literally spelled out in the original request (only
 * /execute's v2 shape was shown), extended here for consistency rather
 * than leaving this one endpoint on the v1 {"data","meta"} convention.
 */
final class MCPDiscoveryControllerV2 extends AbstractMCPDiscoveryController
{
    protected function formatResponse(array $capabilities): JsonResponse
    {
        return response()->json([
            'capabilities' => $capabilities,
            'metadata' => [
                'api_version' => 'v2',
                'count' => count($capabilities),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
