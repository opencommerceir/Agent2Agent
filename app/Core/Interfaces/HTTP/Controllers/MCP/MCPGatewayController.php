<?php

namespace App\Core\Interfaces\HTTP\Controllers\MCP;

use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Application\Actions\EnforceRateLimitAction;
use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\Services\AgentAuthenticationService;
use App\Core\Application\Services\CapabilityExecutionService;
use App\Core\Application\Services\LanguageDetector;
use App\Core\Application\Services\MCPResponseFormatter;
use Illuminate\Http\JsonResponse;

/**
 * POST /mcp/v1/execute — the only place a v1 Agent request turns into
 * anything happening. The full Authenticate -> Authorize -> Route
 * sequence (Decision 007) now lives on AbstractMCPGatewayController
 * (Stage 7, API Versioning) since it's identical for every wire version;
 * this class owns only the one thing that's actually v1-specific: the
 * response envelope shape, {"data": ..., "meta": ...} — unchanged from
 * every version of this class before Stage 7, byte-for-byte, so no
 * existing v1 caller/test sees any difference.
 *
 * No try/catch here: every failure (invalid token, missing permission,
 * unknown capability, bad input, anything unexpected) is a thrown
 * exception left to bubble up to MCPExceptionHandler, registered globally
 * in bootstrap/app.php for mcp/* routes.
 *
 * Hardcodes MemberType::Agent throughout (via the abstract base): MCP is
 * the AI Agent entry point specifically (README "Core Architecture" — AI
 * Agents are first-class consumers), not a general-purpose API for human
 * Users.
 */
final class MCPGatewayController extends AbstractMCPGatewayController
{
    public function __construct(
        AgentAuthenticationService $agentAuthentication,
        EnforceRateLimitAction $enforceRateLimit,
        GetCapabilityAction $getCapability,
        CheckPermissionAction $checkPermission,
        CapabilityExecutionService $capabilityExecution,
        LanguageDetector $languageDetector,
        private readonly MCPResponseFormatter $response,
    ) {
        parent::__construct(
            $agentAuthentication,
            $enforceRateLimit,
            $getCapability,
            $checkPermission,
            $capabilityExecution,
            $languageDetector,
        );
    }

    protected function formatResponse(array $execution, string $capabilityName): JsonResponse
    {
        return $this->response->success($execution['result'], [
            'capability' => $capabilityName,
            'execution_time' => $execution['executionTimeMs'],
        ]);
    }
}
