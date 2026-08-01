<?php

namespace App\Core\Interfaces\HTTP\Controllers\MCP;

use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Application\Actions\EnforceRateLimitAction;
use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\AgentAuthenticationService;
use App\Core\Application\Services\CapabilityExecutionService;
use App\Core\Application\Services\LanguageDetector;
use App\Core\Application\Services\MCPResponseFormatter;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Interfaces\HTTP\Requests\MCP\ExecuteCapabilityRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * POST /mcp/v1/execute — the only place an Agent request turns into
 * anything happening. Strictly Authenticate -> Authorize -> Route ->
 * Format (Decision 007): every step below delegates to a Core Action or
 * Service.
 *
 * No try/catch here anymore. Every failure (invalid token, missing
 * permission, unknown capability, bad input, anything unexpected) is a
 * thrown exception left to bubble up to MCPExceptionHandler, registered
 * globally in bootstrap/app.php for mcp/* routes — this class no longer
 * contains any error-formatting logic of its own, only the happy path.
 *
 * Hardcodes MemberType::Agent throughout, deliberately: MCP is the AI
 * Agent entry point specifically (README "Core Architecture" — AI Agents
 * are first-class consumers), not a general-purpose API for human Users.
 */
final class MCPGatewayController extends Controller
{
    public function __construct(
        private readonly AgentAuthenticationService $agentAuthentication,
        private readonly EnforceRateLimitAction $enforceRateLimit,
        private readonly GetCapabilityAction $getCapability,
        private readonly CheckPermissionAction $checkPermission,
        private readonly CapabilityExecutionService $capabilityExecution,
        private readonly LanguageDetector $languageDetector,
        private readonly MCPResponseFormatter $response,
    ) {
    }

    public function execute(ExecuteCapabilityRequest $request): JsonResponse
    {
        $agent = $this->agentAuthentication->authenticateFromRequest($request);

        $this->enforceRateLimit->authorize($agent->id);

        $capabilityName = $request->string('capability')->toString();
        $input = $request->input('input', []);

        $capability = $this->getCapability->execute($capabilityName);

        foreach ($capability->requiredPermissions as $permissionKey) {
            $this->checkPermission->authorize(MemberType::Agent, $agent->id, $agent->tenantId, $permissionKey);
        }

        $language = $this->languageDetector->detect($request, $agent->tenantId);
        $execution = $this->capabilityExecution->execute($capability, $input, AuthContext::forAgent($agent, $language));

        return $this->response->success($execution['result'], [
            'capability' => $capabilityName,
            'execution_time' => $execution['executionTimeMs'],
        ]);
    }
}
