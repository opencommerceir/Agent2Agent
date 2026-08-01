<?php

namespace App\Core\Interfaces\HTTP\Controllers\MCP;

use Illuminate\Http\JsonResponse;

/**
 * POST /mcp/v2/execute — same Authenticate -> Authorize -> Route sequence
 * as v1 (AbstractMCPGatewayController), a deliberately different response
 * envelope: {"result": ..., "metadata": {"api_version": "v2",
 * "capability": ..., "execution_time": ..., "timestamp": ...}} — `result`
 * replaces `data`, `metadata` replaces `meta` and gains `api_version`/
 * `timestamp`, per the migration guide (docs/api/migration/v1-to-v2.md).
 *
 * Business logic (capability lookup, permission checks, execution) is
 * byte-for-byte identical to v1 — Stage 7's whole point is that a v2
 * client gets a different envelope around the same underlying result, not
 * a different platform. No new v2-only capabilities/behavior exist yet;
 * see that migration guide's own "New Features in v2" section for what's
 * planned-but-not-built (batch operations, webhooks, real-time updates —
 * explicitly out of scope for this stage, which is response-shape
 * versioning infrastructure only).
 */
final class MCPGatewayControllerV2 extends AbstractMCPGatewayController
{
    protected function formatResponse(array $execution, string $capabilityName): JsonResponse
    {
        return response()->json([
            'result' => $execution['result'],
            'metadata' => [
                'api_version' => 'v2',
                'capability' => $capabilityName,
                'execution_time' => $execution['executionTimeMs'],
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
