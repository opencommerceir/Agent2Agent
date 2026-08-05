<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
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
use Database\Seeders\FinanceCapabilitiesSeeder;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Database\Seeders\ReportingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The literal end-to-end scenario from Phase 6 Stage 6's own request
 * (§7.31): POST /api/agents/ceo -> pre-execution reasoning happens and is
 * recorded -> the goal executes -> post-execution reflection happens and
 * is recorded -> both are retrievable afterward via
 * GET /api/agents/reasoning/trace and /explain. Mirrors GoalExecutionTest's
 * own CEO sales-goal scenario (§7.27) plus the reasoning surface this
 * stage adds on top of it.
 */
class SelfReflectionTest extends TestCase
{
    use RefreshDatabase;

    private const REQUIRED_PERMISSIONS = [
        'agent.goals.execute',
        'agent.executions.read',
        'agent.reasoning.read',
        'reporting.sales.read',
        'reporting.revenue.read',
        'analytics.kpis.read',
        'commerce.coupons.create',
        'notifications.messages.send',
        'notifications.templates.manage',
        'crm.tickets.read',
        'finance.invoices.read',
    ];

    public function test_executingAGoal_producesAndPersistsBothReasoningTraces(): void
    {
        [$tenantId, , $token] = $this->registerAgentWithPermissions(self::REQUIRED_PERMISSIONS);
        $this->seedPromotionTemplate($tenantId);

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $executionId = $response->json('id');

        $preReasoning = $response->json('pre_reasoning');
        $postReasoning = $response->json('post_reasoning');

        $this->assertNotNull($preReasoning);
        $this->assertSame('pre_execution', $preReasoning['reasoning_type']);
        $this->assertNotEmpty($preReasoning['thoughts']);
        $this->assertIsFloat($preReasoning['confidence_score']);
        $this->assertNotEmpty($preReasoning['decision']);

        $this->assertNotNull($postReasoning);
        $this->assertSame('post_execution', $postReasoning['reasoning_type']);
        $this->assertSame($executionId, $postReasoning['execution_id']);

        $this->assertNotEmpty($response->json('explanation'));
        $this->assertStringContainsString('Increase sales by 15% this week', $response->json('explanation'));

        $this->assertDatabaseHas('reasoning_traces', [
            'tenant_id' => $tenantId,
            'execution_id' => $executionId,
            'reasoning_type' => 'pre_execution',
        ]);
        $this->assertDatabaseHas('reasoning_traces', [
            'tenant_id' => $tenantId,
            'execution_id' => $executionId,
            'reasoning_type' => 'post_execution',
        ]);
        $this->assertDatabaseCount('reasoning_traces', 2);
    }

    public function test_getReasoningTrace_returnsBothTracesAfterward(): void
    {
        [$tenantId, , $token] = $this->registerAgentWithPermissions(self::REQUIRED_PERMISSIONS);
        $this->seedPromotionTemplate($tenantId);

        $execute = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);
        $executionId = $execute->json('id');

        $trace = $this->getJson("/api/agents/reasoning/trace?execution_id={$executionId}", ['Authorization' => "Bearer {$token}"]);

        $trace->assertStatus(200);
        $trace->assertJsonPath('pre_reasoning.reasoning_type', 'pre_execution');
        $trace->assertJsonPath('post_reasoning.reasoning_type', 'post_execution');
        $trace->assertJsonPath('pre_reasoning.execution_id', $executionId);
    }

    public function test_explainReasoning_rendersAHumanReadableExplanation(): void
    {
        [$tenantId, , $token] = $this->registerAgentWithPermissions(self::REQUIRED_PERMISSIONS);
        $this->seedPromotionTemplate($tenantId);

        $execute = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);
        $executionId = $execute->json('id');

        $explain = $this->getJson("/api/agents/reasoning/explain?execution_id={$executionId}", ['Authorization' => "Bearer {$token}"]);

        $explain->assertStatus(200);
        $this->assertStringContainsString('Pre-Execution Reasoning', $explain->json('explanation'));
        $this->assertStringContainsString('Post-Execution Reflection', $explain->json('explanation'));
    }

    public function test_explainReasoning_forAnUnknownExecution_returns404(): void
    {
        [, , $token] = $this->registerAgentWithPermissions(self::REQUIRED_PERMISSIONS);

        $explain = $this->getJson('/api/agents/reasoning/explain?execution_id=999999', ['Authorization' => "Bearer {$token}"]);

        $explain->assertStatus(404);
    }

    public function test_mcpCapabilities_reachTheSameReasoningTraces(): void
    {
        [$tenantId, , $token] = $this->registerAgentWithPermissions(self::REQUIRED_PERMISSIONS);
        $this->seedPromotionTemplate($tenantId);

        $execute = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);
        $executionId = $execute->json('id');

        $trace = $this->postJson('/mcp/v1/execute', [
            'capability' => 'agent.reasoning.trace',
            'input' => ['execution_id' => $executionId],
        ], ['Authorization' => "Bearer {$token}"]);

        $trace->assertStatus(200);
        $trace->assertJsonPath('data.pre_reasoning.reasoning_type', 'pre_execution');

        $explain = $this->postJson('/mcp/v1/execute', [
            'capability' => 'agent.reasoning.explain',
            'input' => ['execution_id' => $executionId],
        ], ['Authorization' => "Bearer {$token}"]);

        $explain->assertStatus(200);
        $this->assertNotEmpty($explain->json('data.explanation'));
    }

    public function test_reasoningEndpoints_rejectAnAgentMissingThePermission(): void
    {
        [, , $token] = $this->registerAgentWithPermissions(['agent.goals.execute']);

        $response = $this->getJson('/api/agents/reasoning/trace?execution_id=1', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
    }

    public function test_reasoningTraces_areTenantIsolated(): void
    {
        [$tenantA, , $tokenA] = $this->registerAgentWithPermissions(self::REQUIRED_PERMISSIONS);
        $this->seedPromotionTemplate($tenantA);

        $execute = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $executionId = $execute->json('id');

        [, , $tokenB] = $this->registerAgentWithPermissions(self::REQUIRED_PERMISSIONS);

        $trace = $this->getJson("/api/agents/reasoning/trace?execution_id={$executionId}", ['Authorization' => "Bearer {$tokenB}"]);

        $trace->assertStatus(200);
        $this->assertNull($trace->json('pre_reasoning'));
        $this->assertNull($trace->json('post_reasoning'));
    }

    public function test_whenTheLlmFails_theGoalStillExecutesUsingSimpleReasoning(): void
    {
        config(['agent-orchestrator.reasoning.type' => 'llm']);

        $this->app->bind(LLMClientInterface::class, fn () => new class implements LLMClientInterface {
            public function complete(string $prompt, array $options = []): string
            {
                throw new RuntimeException('network unreachable');
            }

            public function completeStructured(string $prompt, string $schema, array $options = []): array
            {
                throw new RuntimeException('network unreachable');
            }
        });

        [$tenantId, , $token] = $this->registerAgentWithPermissions(self::REQUIRED_PERMISSIONS);
        $this->seedPromotionTemplate($tenantId);

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('pre_reasoning'));
        $this->assertStringContainsString('Deterministic', $response->json('pre_reasoning.explanation'));
        $this->assertNotNull($response->json('post_reasoning'));
        $this->assertStringContainsString('Deterministic', $response->json('post_reasoning.explanation'));
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        $this->seed(ReportingCapabilitiesSeeder::class);
        $this->seed(AnalyticsCapabilitiesSeeder::class);
        $this->seed(NotificationsCapabilitiesSeeder::class);
        $this->seed(CRMCapabilitiesSeeder::class);
        $this->seed(FinanceCapabilitiesSeeder::class);
        $this->seed(AgentOrchestratorCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'CEO Agent', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Orchestrator', 'orchestrator-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $agent->id, $token];
    }

    private function seedPromotionTemplate(int $tenantId): void
    {
        app(CreateTemplateAction::class)->execute(
            tenantId: $tenantId,
            type: 'promotion_announcement',
            channelType: 'email',
            subjectTemplate: '{{discount_percent}}% off this week',
            bodyTemplate: 'Enjoy {{discount_percent}}% off your next order.',
        );
    }
}
