<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Controllers;

use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Application\Actions\EnforceRateLimitAction;
use App\Core\Application\Services\AgentAuthenticationService;
use App\Core\Domain\ValueObjects\MemberType;
use App\Http\Controllers\Controller;
use App\Modules\AgentOrchestrator\Application\Actions\GetExecutionInsightsAction;
use App\Modules\AgentOrchestrator\Application\Actions\SuggestExecutionPlanAction;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The HTTP boundary for Execution Memory & Learning's own read surface
 * (Phase 6, Stage 4, §7.29) — `agent_execution.history` is deliberately
 * **not** repeated here as `/memory/history`: it would be functionally
 * identical to the already-existing `GET /api/agents/executions`
 * (`AgentController::listExecutions`, backed by the same
 * `ExecutionMemoryRepositoryInterface::list()` this stage reuses rather
 * than duplicating) — see `docs/execution-memory.md`'s own "What this
 * stage deliberately did not build" section. Only `insights`/`suggest` are
 * genuinely new reads, so only they get a route — the same "Gateway vs.
 * Discovery" split `AgentController`/`AgentProfileController` already
 * establish, one more Controller for one more distinct concern rather than
 * growing either of those two.
 */
class AgentMemoryController extends Controller
{
    public function __construct(
        private readonly AgentAuthenticationService $agentAuthentication,
        private readonly EnforceRateLimitAction $enforceRateLimit,
        private readonly CheckPermissionAction $checkPermission,
    ) {
    }

    public function insights(Request $request, GetExecutionInsightsAction $action): JsonResponse
    {
        $agent = $this->authenticate($request);

        $agentType = AgentType::from((string) $request->query('agent_type', ''));

        return response()->json([
            'insights' => $action->execute($agent->tenantId, $agentType),
        ]);
    }

    public function suggest(Request $request, SuggestExecutionPlanAction $action): JsonResponse
    {
        $agent = $this->authenticate($request);

        $goalText = (string) $request->input('goal', '');
        $agentType = AgentType::from((string) $request->input('agent_type', ''));

        $plan = $action->execute($goalText, $agentType, $agent->tenantId);

        return response()->json([
            'suggested_plan' => $plan?->toArray(),
        ]);
    }

    private function authenticate(Request $request): object
    {
        $agent = $this->agentAuthentication->authenticateFromRequest($request);
        $this->enforceRateLimit->authorize($agent->id);
        $this->checkPermission->authorize(MemberType::Agent, $agent->id, $agent->tenantId, 'agent.memory.read');

        return $agent;
    }
}
