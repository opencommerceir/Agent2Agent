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
use Database\Seeders\AgentOrchestratorCapabilitiesSeeder;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Database\Seeders\ReportingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The literal end-to-end scenario from this stage's own request:
 * PLANNER_TYPE=llm -> a real (fake, no live API) LLM response drives a
 * real plan -> real execution -> a real result, proving the whole
 * pipeline (prompt built from the real Capability Registry -> LLM
 * response parsed -> ExecutionPlan -> PlanExecutor -> persisted
 * ExecutionResult) works end to end without ever hitting a real network.
 */
class LLMPlannerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_llmPlanner_drivesARealExecutionEndToEnd(): void
    {
        config(['agent-orchestrator.planner.type' => 'llm']);
        $this->app->bind(LLMClientInterface::class, fn () => $this->fakeClientReturning([
            'steps' => [
                ['capability' => 'report.sales.generate', 'input' => ['start_date' => '2026-01-01', 'end_date' => '2026-01-07'], 'priority' => 'high'],
                ['capability' => 'commerce.coupon.create', 'input' => ['code' => 'COUPON-LLM01', 'discount_type' => 'percentage', 'discount_value' => 12]],
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
        $this->assertSame(
            ['report.sales.generate', 'commerce.coupon.create'],
            array_column($response->json('steps'), 'capability'),
        );
        foreach ($response->json('steps') as $step) {
            $this->assertSame('completed', $step['status']);
        }
        $response->assertJsonPath('status', 'completed');
        $this->assertDatabaseHas('coupons', ['code' => 'COUPON-LLM01']);
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        $this->seed(ReportingCapabilitiesSeeder::class);
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
