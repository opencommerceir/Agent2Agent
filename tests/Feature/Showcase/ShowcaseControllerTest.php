<?php

namespace Tests\Feature\Showcase;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\AgentOrchestrator\Domain\Services\LLMClientInterface;
use App\Modules\Notifications\Application\Actions\CreateTemplateAction;
use Database\Seeders\AgentOrchestratorCapabilitiesSeeder;
use Database\Seeders\AnalyticsCapabilitiesSeeder;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Database\Seeders\CRMCapabilitiesSeeder;
use Database\Seeders\DemoShowcaseSeeder;
use Database\Seeders\FinanceCapabilitiesSeeder;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Database\Seeders\ReportingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the /showcase Interfaces-layer surface end to end, without
 * running the full DemoShowcaseSeeder (that's DemoShowcaseSeederTest's
 * own, slower job) — this test only needs a Tenant/Agent that matches
 * DemoShowcaseSeeder's own well-known slug/name, the same minimal
 * fixture shape GoalExecutionTest's own registerAgentWithPermissions()
 * already establishes for the underlying ExecuteGoalAction.
 */
class ShowcaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private const REQUIRED_PERMISSIONS = [
        'agent.goals.execute',
        'agent.executions.read',
        'reporting.sales.read',
        'reporting.revenue.read',
        'analytics.kpis.read',
        'commerce.coupons.create',
        'notifications.messages.send',
        'notifications.templates.manage',
        'crm.tickets.read',
        'finance.invoices.read',
        'agent.collaboration.delegate',
        'agent.collaboration.read',
    ];

    public function test_index_showsExplicitErrorWhenDemoTenantIsNotSeeded(): void
    {
        $response = $this->get('/showcase');

        $response->assertStatus(200);
        $response->assertViewHas('demoMissing', true);
    }

    public function test_index_createsASessionTokenForTheSeededDemoAgent(): void
    {
        $this->seedDemoTenant();

        $response = $this->get('/showcase');

        $response->assertStatus(200);
        $response->assertViewHas('demoMissing', false);
        $this->assertNotEmpty(session('showcase_agent_token'));
    }

    public function test_chat_withoutAPriorIndexVisit_returnsAnExplicit401(): void
    {
        $this->seedDemoTenant();

        $response = $this->postJson('/showcase/chat', ['goal' => 'Increase sales', 'agent_type' => 'ceo']);

        $response->assertStatus(401);
    }

    public function test_chat_returnsARealExecutionResultDataPayload(): void
    {
        $this->seedDemoTenant();

        $this->get('/showcase');

        $response = $this->postJson('/showcase/chat', [
            'goal' => 'Increase sales by 15% this week',
            'agent_type' => 'ceo',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('agent_type', 'ceo');
        $response->assertJsonPath('status', 'completed');
        $response->assertJsonCount(4, 'steps');
        $response->assertJsonStructure([
            'id', 'goal', 'agent_type', 'steps', 'summary', 'status', 'execution_time',
            'pre_reasoning' => ['thoughts', 'alternatives', 'confidence_score', 'decision'],
            'post_reasoning' => ['thoughts', 'alternatives', 'confidence_score', 'decision'],
            'explanation',
        ]);

        $capabilities = array_column($response->json('steps'), 'capability');
        $this->assertSame([
            'report.sales.generate',
            'analytics.kpi.calculate',
            'commerce.coupon.create',
            'notification.message.send',
        ], $capabilities);
    }

    public function test_chat_withUnknownPersona_returns422(): void
    {
        $this->seedDemoTenant();

        $this->get('/showcase');

        $response = $this->postJson('/showcase/chat', ['goal' => 'Do something', 'agent_type' => 'not-a-real-persona']);

        $response->assertStatus(422);
    }

    /**
     * Phase 2 (§7.33) — the new `delegate` planning_rules entry
     * (config/agents/ceo.php) resolves `agent.collaboration.delegate`,
     * which runs the *unmodified* ExecuteGoalAction for the Sales persona
     * under the caller's own real permissions — a real, nested
     * ExecutionResultData, not a stub.
     */
    public function test_chat_delegateGoal_producesARealDelegationStepWithANestedSalesExecution(): void
    {
        $this->seedDemoTenant();

        $this->get('/showcase');

        $response = $this->postJson('/showcase/chat', [
            'goal' => 'Delegate this promotional campaign to another agent',
            'agent_type' => 'ceo',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('agent_type', 'ceo');
        $response->assertJsonCount(1, 'steps');
        $response->assertJsonPath('steps.0.capability', 'agent.collaboration.delegate');
        $response->assertJsonPath('steps.0.status', 'completed');
        $response->assertJsonPath('steps.0.input.from_agent', 'ceo');
        $response->assertJsonPath('steps.0.input.to_agent', 'sales');
        $response->assertJsonPath('steps.0.output.result.agent_type', 'sales');
        $response->assertJsonPath('steps.0.output.result.status', 'completed');

        $nestedCapabilities = array_column($response->json('steps.0.output.result.steps'), 'capability');
        $this->assertSame(['commerce.coupon.create', 'notification.message.send'], $nestedCapabilities);

        $this->assertDatabaseHas('delegation_requests', [
            'from_agent_type' => 'ceo',
            'to_agent_type' => 'sales',
            'status' => 'completed',
        ]);
    }

    /**
     * Regression proof: the new `delegate` rule is declared first in
     * config/agents/ceo.php's own planning_rules, but every pre-existing
     * rule (`sales`/`revenue`/`inventory`/`default`) must still resolve
     * exactly as before — CEOAgentTest/GoalExecutionTest already cover
     * `sales`/`revenue` end to end; this proves `inventory` too, directly
     * through the Showcase surface itself.
     */
    public function test_chat_ceoInventoryGoal_stillProducesTheSingleUnaffectedAnalyticsStep(): void
    {
        $this->seedDemoTenant();

        $this->get('/showcase');

        $response = $this->postJson('/showcase/chat', [
            'goal' => 'Check our inventory levels',
            'agent_type' => 'ceo',
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'steps');
        $response->assertJsonPath('steps.0.capability', 'analytics.kpi.calculate');
        $response->assertJsonPath('steps.0.status', 'completed');
    }

    /**
     * Reproduces the exact real bug caught live against a genuine
     * `php artisan demo:reset` tenant, not by any fixture-driven test —
     * DemoShowcaseSeeder pre-seeds a real CEO execution for "Increase
     * sales by 15% this week", which creates a learned `ExecutionPattern`
     * keyed on the single word "sales" (PatternExtractor::KEYWORDS).
     * `ExecuteGoalAction` consults `LearningServiceInterface::suggestPlan()`
     * *before* either `PlannerInterface` implementation (§7.29) —
     * `ExecutionPattern::matches()` is a plain substring check, so the
     * original delegate suggestion text ("...to the Sales team") matched
     * that already-learned pattern and silently reused its old 4-step
     * plan, never reaching `config/agents/ceo.php`'s own new `delegate`
     * rule at all. Fixed by rewording the suggestion's own goal text to
     * avoid every `PatternExtractor::KEYWORDS` word; this test proves the
     * fix by explicitly pre-seeding the exact colliding pattern first,
     * the one thing `test_chat_delegateGoal_producesARealDelegationStepWithANestedSalesExecution`
     * itself doesn't exercise (a fresh Tenant with no prior Executions).
     */
    public function test_chat_delegateGoal_stillDelegatesEvenWhenASalesPatternWasAlreadyLearned(): void
    {
        $this->seedDemoTenant();

        $this->get('/showcase');

        // Seed the exact colliding learned pattern first — a real CEO
        // "sales" execution, run through the unmodified ExecuteGoalAction,
        // the same way DemoShowcaseSeeder's own seedExecutions() does.
        $priorRun = $this->postJson('/showcase/chat', [
            'goal' => 'Increase sales by 15% this week',
            'agent_type' => 'ceo',
        ]);
        $priorRun->assertStatus(200);
        $this->assertDatabaseHas('execution_patterns', ['agent_type' => 'ceo', 'goal_pattern' => 'sales']);

        $response = $this->postJson('/showcase/chat', [
            'goal' => 'Delegate this promotional campaign to another agent',
            'agent_type' => 'ceo',
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'steps');
        $response->assertJsonPath('steps.0.capability', 'agent.collaboration.delegate');
        $response->assertJsonPath('steps.0.output.result.agent_type', 'sales');
    }

    /**
     * Phase 3 (§7.33) — `use_real_ai` overrides `agent-orchestrator.{planner,reasoning}.type`/
     * `.llm.provider` for this one request only, exactly the mechanism
     * `PlannerConfigTest`/`ReasoningConfigTest`/`OpenRouterIntegrationTest`
     * already prove (`AgentOrchestratorServiceProvider::register()` binds
     * `PlannerInterface`/`ReasoningEngineInterface`/`LLMClientInterface`
     * as closures re-evaluated on every resolution, never `singleton()`),
     * reached here from a real Controller instead of a test rebinding
     * config directly. `ExecuteGoalAction` is resolved manually inside
     * `chat()` *after* the override — proven indirectly here by the
     * response actually reflecting the fake LLM's own plan (1 step) rather
     * than `ceo.php`'s own deterministic `sales` rule (4 steps).
     */
    public function test_chat_withUseRealAiTrue_usesTheLlmPathForThisRequestOnlyAndRestoresConfigAfterward(): void
    {
        $this->seedDemoTenant();
        $this->get('/showcase');

        $this->assertSame('deterministic', config('agent-orchestrator.planner.type'));
        $this->assertSame('simple', config('agent-orchestrator.reasoning.type'));

        $this->app->bind(LLMClientInterface::class, fn () => $this->fakeClientReturning([
            'steps' => [
                ['capability' => 'report.sales.generate', 'input' => ['start_date' => '2026-01-01', 'end_date' => '2026-01-07'], 'priority' => 'high'],
            ],
            'thoughts' => ['A real LLM thought, not a deterministic rule.'],
            'alternatives' => [],
            'confidence' => 0.91,
            'decision' => 'Fake LLM decision.',
            'explanation' => 'Fake LLM explanation.',
        ]));

        $response = $this->postJson('/showcase/chat', [
            'goal' => 'Increase sales by 15% this week',
            'agent_type' => 'ceo',
            'use_real_ai' => true,
        ]);

        $response->assertStatus(200);
        // The fake LLM's own 1-step plan, not ceo.php's real 4-step
        // `sales` rule — proves the LLM path actually ran.
        $response->assertJsonCount(1, 'steps');
        $response->assertJsonPath('steps.0.capability', 'report.sales.generate');
        $response->assertJsonPath('pre_reasoning.confidence_score', 0.91);
        $response->assertJsonPath('pre_reasoning.decision', 'Fake LLM decision.');

        // Config is back to its pre-request value — no leak into
        // whatever code runs next in this process.
        $this->assertSame('deterministic', config('agent-orchestrator.planner.type'));
        $this->assertSame('simple', config('agent-orchestrator.reasoning.type'));

        // And a real, independent follow-up request without the toggle
        // provably resolves a real deterministic plan again — not just
        // the raw config value, the actual behavior. Deliberately a
        // *different* goal ("revenue," not "sales") from the one just
        // run above: that first call's own success just taught Execution
        // Memory a real "sales" -> `report.sales.generate` pattern
        // (§7.29) — reusing the identical goal text here would correctly
        // short-circuit straight to that learned pattern before ever
        // reaching PlannerInterface at all, which would prove nothing
        // about config leaking (a real behavior, not a bug — the exact
        // same interaction Phase 2's own §7.33 bug writeup documents).
        $followUp = $this->postJson('/showcase/chat', [
            'goal' => 'Review our revenue this quarter',
            'agent_type' => 'ceo',
        ]);
        $followUp->assertStatus(200);
        $followUp->assertJsonCount(2, 'steps');
        $followUp->assertJsonPath('steps.0.capability', 'report.revenue.generate');
        $followUp->assertJsonPath('steps.1.capability', 'analytics.kpi.calculate');
    }

    private function fakeClientReturning(array $response): LLMClientInterface
    {
        return new class($response) implements LLMClientInterface
        {
            public function __construct(private readonly array $response)
            {
            }

            public function complete(string $prompt, array $options = []): string
            {
                return '';
            }

            public function completeStructured(string $prompt, string $schema, array $options = []): array
            {
                return $this->response;
            }
        };
    }

    private function seedDemoTenant(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        $this->seed(ReportingCapabilitiesSeeder::class);
        $this->seed(AnalyticsCapabilitiesSeeder::class);
        $this->seed(NotificationsCapabilitiesSeeder::class);
        $this->seed(CRMCapabilitiesSeeder::class);
        $this->seed(FinanceCapabilitiesSeeder::class);
        $this->seed(AgentOrchestratorCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Demo Showcase Store', DemoShowcaseSeeder::TENANT_SLUG);
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Demo Showcase HQ', DemoShowcaseSeeder::TENANT_SLUG.'-hq');
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, DemoShowcaseSeeder::DEMO_AGENT_NAME, 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Showcase Operator', DemoShowcaseSeeder::TENANT_SLUG.'-operator');

        foreach (self::REQUIRED_PERMISSIONS as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        app(CreateTemplateAction::class)->execute(
            tenantId: $tenant->id,
            type: 'promotion_announcement',
            channelType: 'email',
            subjectTemplate: '{{discount_percent}}% off this week',
            bodyTemplate: 'Enjoy {{discount_percent}}% off your next order.',
        );
    }
}
