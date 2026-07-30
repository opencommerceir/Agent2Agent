<?php

namespace App\Core\Interfaces\HTTP\Controllers\MCP;

use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\Services\AgentAuthenticationService;
use App\Core\Application\Services\CapabilityExecutionService;
use App\Core\Application\Services\MCPResponseFormatter;
use App\Core\Domain\Exceptions\AgentNotActiveException;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Core\Domain\Exceptions\InvalidAgentTokenException;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Interfaces\HTTP\Requests\MCP\ExecuteCapabilityRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * POST /mcp/v1/execute — the only place an Agent request turns into
 * anything happening. Strictly Authenticate -> Authorize -> Route ->
 * Format (Decision 007): every step below delegates to a Core Action or
 * Service; this class contains no business logic itself, only the order
 * to call things in and which HTTP status/error code each failure maps to.
 *
 * Hardcodes MemberType::Agent throughout, deliberately: MCP is the AI
 * Agent entry point specifically (README "Core Architecture" — AI Agents
 * are first-class consumers), not a general-purpose API for human Users,
 * so there is no ambiguity here to make configurable.
 */
final class MCPGatewayController extends Controller
{
    public function __construct(
        private readonly AgentAuthenticationService $agentAuthentication,
        private readonly GetCapabilityAction $getCapability,
        private readonly CheckPermissionAction $checkPermission,
        private readonly CapabilityExecutionService $capabilityExecution,
        private readonly MCPResponseFormatter $response,
    ) {
    }

    public function execute(ExecuteCapabilityRequest $request): JsonResponse
    {
        try {
            $agent = $this->agentAuthentication->authenticateFromRequest($request);
        } catch (InvalidAgentTokenException|AgentNotActiveException $e) {
            return $this->response->error('UNAUTHORIZED', $e->getMessage(), 401);
        }

        $capabilityName = $request->string('capability')->toString();
        $input = $request->input('input', []);

        try {
            $capability = $this->getCapability->execute($capabilityName);
        } catch (CapabilityNotFoundException $e) {
            return $this->response->error('CAPABILITY_NOT_FOUND', $e->getMessage(), 404);
        }

        foreach ($capability->requiredPermissions as $permissionKey) {
            $allowed = $this->checkPermission->execute(
                MemberType::Agent,
                $agent->id,
                $agent->tenantId,
                $permissionKey,
            );

            if (! $allowed) {
                return $this->response->error(
                    'FORBIDDEN',
                    "Agent lacks required permission [{$permissionKey}] for capability [{$capabilityName}].",
                    403,
                );
            }
        }

        try {
            $execution = $this->capabilityExecution->execute($capability, $input);
        } catch (InvalidArgumentException $e) {
            return $this->response->error('INVALID_INPUT', $e->getMessage(), 422);
        }

        return $this->response->success($execution['result'], [
            'capability' => $capabilityName,
            'execution_time' => $execution['executionTimeMs'],
        ]);
    }
}
