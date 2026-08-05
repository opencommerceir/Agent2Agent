<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Controllers;

use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Application\Actions\EnforceRateLimitAction;
use App\Core\Application\Services\AgentAuthenticationService;
use App\Core\Domain\ValueObjects\MemberType;
use App\Http\Controllers\Controller;
use App\Modules\AgentOrchestrator\Application\Actions\ExplainReasoningAction;
use App\Modules\AgentOrchestrator\Application\Actions\GetReasoningTraceAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The HTTP boundary for Self-Reflection & Reasoning's own read surface
 * (Phase 6, Stage 6, §7.31) — the same "Gateway vs. Discovery vs. Memory
 * vs. [this]" split `AgentController`/`AgentProfileController`/
 * `AgentMemoryController` already establish, one more Controller for one
 * more distinct concern rather than growing any of those three.
 */
class AgentReasoningController extends Controller
{
    public function __construct(
        private readonly AgentAuthenticationService $agentAuthentication,
        private readonly EnforceRateLimitAction $enforceRateLimit,
        private readonly CheckPermissionAction $checkPermission,
    ) {
    }

    public function trace(Request $request, GetReasoningTraceAction $action): JsonResponse
    {
        $agent = $this->authenticate($request);

        $executionId = (int) $request->query('execution_id', 0);

        $traces = $action->execute($agent->tenantId, $executionId);

        return response()->json([
            'pre_reasoning' => $traces['pre_execution']?->toArray(),
            'post_reasoning' => $traces['post_execution']?->toArray(),
        ]);
    }

    public function explain(Request $request, ExplainReasoningAction $action): JsonResponse
    {
        $agent = $this->authenticate($request);

        $executionId = (int) $request->query('execution_id', 0);

        return response()->json([
            'explanation' => $action->execute($agent->tenantId, $executionId),
        ]);
    }

    private function authenticate(Request $request): object
    {
        $agent = $this->agentAuthentication->authenticateFromRequest($request);
        $this->enforceRateLimit->authorize($agent->id);
        $this->checkPermission->authorize(MemberType::Agent, $agent->id, $agent->tenantId, 'agent.reasoning.read');

        return $agent;
    }
}
