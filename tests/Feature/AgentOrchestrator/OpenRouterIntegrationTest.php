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
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Database\Seeders\ReportingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Showcase prep (§7.32) — proves `LLM_PROVIDER=openrouter` plugs into both
 * `LLMPlanner` and `LLMReasoningEngine` exactly the same way
 * `LLM_PROVIDER=openai`/`claude` already do (`LLMPlannerIntegrationTest`
 * is this test's own direct precedent — mirrored closely). `LLMClientInterface`
 * is rebound to a fake in every test — no real network call ever reaches
 * OpenRouter, the same "no live credentials to test honestly against"
 * reasoning every external Connector in this codebase gives.
 */
class OpenRouterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_openrouterProvider_drivesARealPlannerExecutionEndToEnd(): void
    {
        config(['agent-orchestrator.llm.provider' => 'openrouter']);
        config(['agent-orchestrator.planner.type' => 'llm']);
        $this->app->bind(LLMClientInterface::class, fn () => $this->fakeClientReturning([
            'steps' => [
                ['capability' => 'report.sales.generate', 'input' => ['start_date' => '2026-01-01', 'end_date' => '2026-01-07'], 'priority' => 'high'],
                ['capability' => 'commerce.coupon.create', 'input' => ['code' => 'COUPON-OR001', 'discount_type' => 'percentage', 'discount_value' => 10]],
            ],
        ]));

        [, , $token] = $this->registerAgentWithPermissions([
            'agent.goals.execute', 'reporting.sales.read', 'commerce.coupons.create',
        ]);

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Analyze last month\'s performance and suggest improvements',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'steps');
        $response->assertJsonPath('status', 'completed');
        $this->assertDatabaseHas('coupons', ['code' => 'COUPON-OR001']);
    }

    public function test_openrouterProvider_drivesRealReasoningEndToEnd(): void
    {
        config(['agent-orchestrator.llm.provider' => 'openrouter']);
        config(['agent-orchestrator.reasoning.type' => 'llm']);
        // PLANNER_TYPE stays deterministic (phpunit.xml default) — only
        // reasoning consults the fake LLMClientInterface this test.
        $this->app->bind(LLMClientInterface::class, fn () => $this->fakeClientReturning([
            'thoughts' => ['Goal requires a 15% increase.', 'Similar goal last month succeeded.'],
            'confidence' => 0.85,
            'decision' => 'Use a targeted coupon campaign on top-selling products.',
            'explanation' => 'Based on past success with a similar strategy.',
        ]));

        [, , $token] = $this->registerAgentWithPermissions([
            'agent.goals.execute', 'reporting.sales.read', 'reporting.revenue.read',
            'analytics.kpis.read', 'commerce.coupons.create', 'notifications.messages.send',
        ]);

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('pre_reasoning.confidence_score', 0.85);
        $response->assertJsonPath('pre_reasoning.decision', 'Use a targeted coupon campaign on top-selling products.');
        $this->assertNotNull($response->json('post_reasoning'));
    }

    public function test_openrouterFailure_fallsBackGracefullyForBothPlannerAndReasoning(): void
    {
        config(['agent-orchestrator.llm.provider' => 'openrouter']);
        config(['agent-orchestrator.planner.type' => 'llm']);
        config(['agent-orchestrator.reasoning.type' => 'llm']);
        $this->app->bind(LLMClientInterface::class, fn () => new class implements LLMClientInterface {
            public function complete(string $prompt, array $options = []): string
            {
                throw new RuntimeException('OpenRouter unreachable');
            }

            public function completeStructured(string $prompt, string $schema, array $options = []): array
            {
                throw new RuntimeException('OpenRouter unreachable');
            }
        });

        [$tenantId, , $token] = $this->registerAgentWithPermissions([
            'agent.goals.execute', 'reporting.sales.read', 'reporting.revenue.read',
            'analytics.kpis.read', 'commerce.coupons.create', 'notifications.messages.send',
            'notifications.templates.manage',
        ]);
        $this->seedPromotionTemplate($tenantId);

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);

        // LLMPlanner falls back to DeterministicPlanner, LLMReasoningEngine
        // falls back to SimpleReasoningEngine — a broken OpenRouter client
        // never turns into a hard failure for the caller.
        $response->assertStatus(200);
        $response->assertJsonPath('status', 'completed');
        $this->assertStringContainsString('Deterministic', $response->json('pre_reasoning.explanation'));
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

    private function fakeClientReturning(array $response): LLMClientInterface
    {
        return new class($response) implements LLMClientInterface {
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
}
