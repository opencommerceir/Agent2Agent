<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Controllers;

use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Application\Actions\EnforceRateLimitAction;
use App\Core\Application\Services\AgentAuthenticationService;
use App\Core\Domain\ValueObjects\MemberType;
use App\Http\Controllers\Controller;
use App\Modules\AgentOrchestrator\Application\Actions\GetAgentProfileAction;
use App\Modules\AgentOrchestrator\Application\Actions\ListAgentProfilesAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The HTTP boundary for this module's own Agent persona profiles (§7.27)
 * — a separate Controller from `AgentController` (goals/executions), the
 * same "Gateway vs. Discovery" split `MCPGatewayController`/
 * `MCPDiscoveryController` already establish for the platform-wide MCP
 * surface, rather than growing one Controller to cover three unrelated
 * concerns.
 *
 * A Profile is platform-level config, not per-tenant data — but reading
 * one is still gated by the calling Agent's own `agent.profiles.read`
 * permission grant, the identical mechanism every other capability in
 * this codebase uses, regardless of what the underlying data actually is.
 */
class AgentProfileController extends Controller
{
    public function __construct(
        private readonly AgentAuthenticationService $agentAuthentication,
        private readonly EnforceRateLimitAction $enforceRateLimit,
        private readonly CheckPermissionAction $checkPermission,
    ) {
    }

    public function index(Request $request, ListAgentProfilesAction $action): JsonResponse
    {
        $agent = $this->authenticate($request);

        $profiles = $action->execute();

        return response()->json([
            'profiles' => array_map(fn ($profile) => $profile->toArray(), $profiles),
        ]);
    }

    public function show(Request $request, string $agentType, GetAgentProfileAction $action): JsonResponse
    {
        $agent = $this->authenticate($request);

        $profile = $action->execute($agentType);

        return response()->json($profile->toArray());
    }

    private function authenticate(Request $request): object
    {
        $agent = $this->agentAuthentication->authenticateFromRequest($request);
        $this->enforceRateLimit->authorize($agent->id);
        $this->checkPermission->authorize(MemberType::Agent, $agent->id, $agent->tenantId, 'agent.profiles.read');

        return $agent;
    }
}
