<?php

namespace App\Http\Controllers\Showcase;

use App\Core\Application\Actions\AuthenticateAgentAction;
use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Application\Actions\EnforceRateLimitAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\DTOs\AuthContext;
use App\Core\Domain\Exceptions\AgentNotActiveException;
use App\Core\Domain\Exceptions\InvalidAgentTokenException;
use App\Core\Domain\Exceptions\PermissionDeniedException;
use App\Core\Domain\Exceptions\RateLimitExceededException;
use App\Core\Domain\Entities\Agent;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Http\Controllers\Controller;
use App\Modules\AgentOrchestrator\Application\Actions\ExecuteGoalAction;
use App\Modules\AgentOrchestrator\Application\Actions\ExplainReasoningAction;
use App\Modules\AgentOrchestrator\Application\Actions\GetExecutionResultAction;
use App\Modules\AgentOrchestrator\Application\Actions\GetReasoningTraceAction;
use App\Modules\AgentOrchestrator\Application\Actions\ListExecutionsAction;
use App\Modules\AgentOrchestrator\Domain\Exceptions\ExecutionNotFoundException;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use Database\Seeders\DemoShowcaseSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * The Interfaces-layer surface for the `/showcase` chat UI — a thin,
 * presentational consumer of the Agent Orchestrator's own
 * `ExecuteGoalAction`, the exact same Action `/api/agents/{agent_type}`
 * (AgentController, routes/agents.php) and `agent.goal.execute` (MCP)
 * already call. Holds no business logic of its own (HANDOFF §3 pattern
 * #19 — the same rule every Admin Dashboard Controller already follows):
 * every response this controller returns is `ExecutionResultData::toArray()`,
 * unmodified, so the chat UI renders identically whichever `PlannerInterface`/
 * `ReasoningEngineInterface` implementation is actually configured
 * (deterministic or LLM-backed — see config/agent-orchestrator.php).
 *
 * Deliberately outside the Dashboard's own `auth`/`admin` middleware
 * group (routes/web.php) — this is a public demo surface, not a
 * human-operator control panel, and authenticates against the seeded
 * Demo Agent's own bearer-token identity, never a Dashboard `User`
 * session. Each browser session gets its own freshly-minted token
 * (`GenerateAgentTokenAction`, the same Action `php artisan tinker`/every
 * test helper already uses) pointing at the one seeded Demo Agent — never
 * a shared, hardcoded secret.
 *
 * Phase 3 (§7.33) added `history()`/`historyShow()` (read-only, reusing
 * `ListExecutionsAction`/`GetExecutionResultAction`/`GetReasoningTraceAction`/
 * `ExplainReasoningAction` — the last two existed since §7.31 but had no
 * caller anywhere in this codebase until now) and a `use_real_ai` toggle
 * inside `chat()` itself (see that method's own docblock) — no new
 * Controller, no new Action.
 */
class ShowcaseController extends Controller
{
    private const SESSION_TOKEN_KEY = 'showcase_agent_token';

    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly AgentRepositoryInterface $agents,
        private readonly GenerateAgentTokenAction $generateToken,
        private readonly AuthenticateAgentAction $authenticateAgent,
    ) {
    }

    public function index(Request $request): View
    {
        $tenant = $this->tenants->findBySlug(DemoShowcaseSeeder::TENANT_SLUG);

        if ($tenant === null) {
            return view('showcase.index', ['demoMissing' => true]);
        }

        if (! $request->session()->has(self::SESSION_TOKEN_KEY)) {
            $demoAgent = $this->findDemoAgent($tenant->id());

            if ($demoAgent === null) {
                return view('showcase.index', ['demoMissing' => true]);
            }

            $token = $this->generateToken->execute($demoAgent->id(), 'showcase-session')->plainToken;
            $request->session()->put(self::SESSION_TOKEN_KEY, $token);
        }

        return view('showcase.index', ['demoMissing' => false]);
    }

    /**
     * `ExecuteGoalAction` is deliberately **not** a method-injected
     * parameter here (unlike `EnforceRateLimitAction`/`CheckPermissionAction`
     * above) — Laravel resolves method-injected parameters *before* this
     * method's own body starts running, which would build `ExecuteGoalAction`
     * (and, transitively, whichever `PlannerInterface`/`ReasoningEngineInterface`/
     * `LLMClientInterface` its constructor pulls in) *before* the
     * `use_real_ai` config override below ever runs. Resolved manually via
     * `app(ExecuteGoalAction::class)` instead, after the override — safe
     * only because `AgentOrchestratorServiceProvider::register()` binds
     * all three of those Interfaces as closures re-evaluated on every
     * resolution, never `singleton()` (§7.28/§7.31), the exact mechanism
     * `PlannerConfigTest`/`ReasoningConfigTest` already prove in tests —
     * this is the same mechanism, reached from a real Controller instead.
     */
    public function chat(Request $request, EnforceRateLimitAction $enforceRateLimit, CheckPermissionAction $checkPermission): JsonResponse
    {
        $token = $request->session()->get(self::SESSION_TOKEN_KEY);

        if (! is_string($token) || $token === '') {
            return response()->json(['error' => 'No active showcase session — reload the page.'], 401);
        }

        $agentType = AgentType::tryFrom((string) $request->input('agent_type', ''));

        if ($agentType === null) {
            return response()->json(['error' => 'Unknown persona.'], 422);
        }

        $goalText = trim((string) $request->input('goal', ''));

        if ($goalText === '') {
            return response()->json(['error' => 'Goal text is required.'], 422);
        }

        // Per-request only, restored in the `finally` block below no
        // matter how this method exits — `config()` mutates a single,
        // process-wide array, and this request is not the only code that
        // will run in this PHP process's lifetime (a queue worker, a
        // long-running Octane/Swoole server, or simply the next request
        // php-fpm reuses this worker for). Ordinary php-fpm reuses a fresh
        // process per request and would never actually leak this, but
        // that's an accident of one deployment model, not a guarantee this
        // code should quietly depend on.
        $originalAiConfig = null;

        if ($request->boolean('use_real_ai')) {
            $originalAiConfig = [
                'agent-orchestrator.planner.type' => config('agent-orchestrator.planner.type'),
                'agent-orchestrator.reasoning.type' => config('agent-orchestrator.reasoning.type'),
                'agent-orchestrator.llm.provider' => config('agent-orchestrator.llm.provider'),
            ];

            config([
                'agent-orchestrator.planner.type' => 'llm',
                'agent-orchestrator.reasoning.type' => 'llm',
                'agent-orchestrator.llm.provider' => 'openrouter',
            ]);
        }

        try {
            $agent = $this->authenticateAgent->execute($token);
            $enforceRateLimit->authorize($agent->id);
            $checkPermission->authorize(MemberType::Agent, $agent->id, $agent->tenantId, 'agent.goals.execute');

            $context = AuthContext::forAgent($agent, dashboard_language());

            // If OPENROUTER_API_KEY isn't set, OpenRouterClient still
            // constructs fine and only fails the moment it's actually
            // called — LLMPlanner/LLMReasoningEngine both already catch
            // that and fall back to DeterministicPlanner/SimpleReasoningEngine
            // automatically (§7.28/§7.31, `fallback_to_deterministic`/
            // `fallback_to_simple`, both default `true`). The toggle above
            // never turns into a hard failure for the caller either way —
            // with no real key, it's simply unnoticeable (documented in
            // README's own Showcase Demo section, not left as a surprise).
            $result = app(ExecuteGoalAction::class)->execute($goalText, $agentType, $context);

            return response()->json($result->toArray());
        } catch (InvalidAgentTokenException|AgentNotActiveException $e) {
            return response()->json(['error' => 'Your showcase session has expired — reload the page.'], 401);
        } catch (RateLimitExceededException $e) {
            return response()->json(['error' => $e->getMessage()], 429);
        } catch (PermissionDeniedException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } finally {
            if ($originalAiConfig !== null) {
                config($originalAiConfig);
            }
        }
    }

    /**
     * A lightweight list — goal + persona + status + timestamp — for the
     * history sidebar, scoped to the one seeded demo Tenant (never the
     * whole platform). Reuses `ListExecutionsAction` unmodified, the same
     * Action `agent.execution.list`/`GET /api/agents/executions` already
     * call.
     */
    public function history(ListExecutionsAction $action): JsonResponse
    {
        $tenant = $this->tenants->findBySlug(DemoShowcaseSeeder::TENANT_SLUG);

        if ($tenant === null) {
            return response()->json(['executions' => []]);
        }

        $results = $action->execute($tenant->id(), null, null, 20);

        return response()->json([
            'executions' => array_map(fn ($result) => [
                'id' => $result->id,
                'goal' => $result->goal,
                'agent_type' => $result->agentType,
                'status' => $result->status,
                'created_at' => $result->createdAt,
            ], $results),
        ]);
    }

    /**
     * Re-opens one past Execution read-only, for the history sidebar's own
     * detail view — the two Actions this Phase actually adds a caller for
     * (`GetReasoningTraceAction`/`ExplainReasoningAction` already existed,
     * real and tested, since §7.31, but nothing in this codebase's own
     * `/showcase` surface had called either until now).
     * `GetExecutionResultAction` alone never carries reasoning data (see
     * that Action's own body — `ExecutionResultData::fromEntity()` is
     * called with no `preReasoning`/`postReasoning`/`explanation`
     * arguments), so this method merges the two real traces onto the same
     * `ExecutionResultData::toArray()` shape `chat()` itself returns — the
     * history detail card renders through the identical Blade/Alpine
     * template either way.
     */
    public function historyShow(int $execution, GetExecutionResultAction $getResult, GetReasoningTraceAction $getTrace, ExplainReasoningAction $explain): JsonResponse
    {
        $tenant = $this->tenants->findBySlug(DemoShowcaseSeeder::TENANT_SLUG);

        if ($tenant === null) {
            return response()->json(['error' => 'Demo tenant not seeded.'], 404);
        }

        try {
            $result = $getResult->execute($execution, $tenant->id());
        } catch (ExecutionNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        $traces = $getTrace->execute($tenant->id(), $execution);

        try {
            $explanation = $explain->execute($tenant->id(), $execution);
        } catch (ExecutionNotFoundException) {
            // No trace recorded at all for this execution (e.g. a
            // genuinely uncaught failure between think() and reflect(),
            // §8.92) — a real, documented gap, not an error here.
            $explanation = null;
        }

        $payload = $result->toArray();
        $payload['pre_reasoning'] = $traces['pre_execution']?->toArray();
        $payload['post_reasoning'] = $traces['post_execution']?->toArray();
        $payload['explanation'] = $explanation;

        return response()->json($payload);
    }

    private function findDemoAgent(int $tenantId): ?Agent
    {
        foreach ($this->agents->all() as $agent) {
            if ($agent->tenantId() === $tenantId && $agent->name() === DemoShowcaseSeeder::DEMO_AGENT_NAME) {
                return $agent;
            }
        }

        return null;
    }
}
