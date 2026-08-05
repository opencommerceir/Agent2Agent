<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Controllers;

use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Application\Actions\EnforceRateLimitAction;
use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\AgentAuthenticationService;
use App\Core\Application\Services\LanguageDetector;
use App\Core\Domain\ValueObjects\MemberType;
use App\Http\Controllers\Controller;
use App\Modules\AgentOrchestrator\Application\Actions\ExecuteGoalAction;
use App\Modules\AgentOrchestrator\Application\Actions\GetExecutionResultAction;
use App\Modules\AgentOrchestrator\Application\Actions\ListExecutionsAction;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The HTTP boundary for the Agent Orchestrator's own `/api/agents/*`
 * surface (routes/agents.php) — plays the same role
 * `AbstractMCPGatewayController` plays for `/mcp/*`: authenticate ->
 * rate-limit -> authorize -> execute, reusing the exact same Core
 * building blocks (`AgentAuthenticationService`/`EnforceRateLimitAction`/
 * `CheckPermissionAction`), since Agent bearer-token auth has no Laravel
 * Guard to attach route middleware to (HANDOFF §3 pattern #16 — the same
 * reason rate limiting itself is an explicit Action call, not middleware).
 *
 * Throws, never catches — every exception an Action here can raise
 * (`InvalidAgentTokenException`/`RateLimitExceededException`/
 * `PermissionDeniedException`/a plain `InvalidArgumentException` for an
 * empty Goal/`ExecutionNotFoundException`/`GoalExecutionFailedException`)
 * is mapped to the correct HTTP status by `MCPExceptionHandler`, extended
 * this stage to also cover `api/agents/*` (see that class's own
 * docblock) rather than re-implementing the same exception -> envelope
 * table a second time in this Controller.
 *
 * Holds no business logic — every method is a thin
 * request-in/DTO-out/JSON-out wrapper around this module's own Actions,
 * the same "Controllers hold no business logic" rule the Admin
 * Dashboard's own Controllers already follow (HANDOFF §3 pattern #19).
 */
class AgentController extends Controller
{
    public function __construct(
        private readonly AgentAuthenticationService $agentAuthentication,
        private readonly EnforceRateLimitAction $enforceRateLimit,
        private readonly CheckPermissionAction $checkPermission,
        private readonly LanguageDetector $languageDetector,
    ) {
    }

    public function execute(Request $request, string $agentType, ExecuteGoalAction $action): JsonResponse
    {
        $agent = $this->agentAuthentication->authenticateFromRequest($request);
        $this->enforceRateLimit->authorize($agent->id);
        $this->checkPermission->authorize(MemberType::Agent, $agent->id, $agent->tenantId, 'agent.goals.execute');

        // The route's own {agentType} constraint (ceo|sales|support|finance)
        // already guarantees this — AgentType::from(), not tryFrom(), so an
        // impossible value fails loudly instead of silently.
        $type = AgentType::from($agentType);

        $goalText = (string) $request->input('goal', '');
        $language = $this->languageDetector->detect($request, $agent->tenantId);
        $context = AuthContext::forAgent($agent, $language);

        $result = $action->execute($goalText, $type, $context);

        return response()->json($result->toArray());
    }

    public function getExecution(Request $request, int $execution, GetExecutionResultAction $action): JsonResponse
    {
        $agent = $this->agentAuthentication->authenticateFromRequest($request);
        $this->enforceRateLimit->authorize($agent->id);
        $this->checkPermission->authorize(MemberType::Agent, $agent->id, $agent->tenantId, 'agent.executions.read');

        $result = $action->execute($execution, $agent->tenantId);

        return response()->json($result->toArray());
    }

    public function listExecutions(Request $request, ListExecutionsAction $action): JsonResponse
    {
        $agent = $this->agentAuthentication->authenticateFromRequest($request);
        $this->enforceRateLimit->authorize($agent->id);
        $this->checkPermission->authorize(MemberType::Agent, $agent->id, $agent->tenantId, 'agent.executions.read');

        $agentType = $request->has('agent_type') ? AgentType::tryFrom((string) $request->input('agent_type')) : null;
        $status = $request->input('status');
        $limit = $request->has('limit') ? (int) $request->input('limit') : null;

        $results = $action->execute($agent->tenantId, $agentType, $status, $limit);

        return response()->json([
            'executions' => array_map(fn ($result) => $result->toArray(), $results),
        ]);
    }
}
