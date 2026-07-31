<?php

namespace Tests\Feature\Workflows;

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
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Database\Seeders\WorkflowsCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The real, no-event-faking end-to-end path Workflows' CartAbandonedListener
 * has been scaffolded for since Phase 3.3: the scheduled
 * `commerce:check-abandoned-carts` command marks a stale Cart abandoned ->
 * Commerce's real CartWasAbandoned event fires -> CartAbandonedListener
 * (registered in WorkflowsServiceProvider::boot(), never called directly
 * by this test) reacts -> a real `cart_abandoned` Workflow triggers ->
 * a WorkflowLog row records it. Same "no event faking" style
 * WorkflowsCapabilityTest already uses for InventoryLowListener.
 */
class CartAbandonedListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_abandonedCart_triggersRegisteredWorkflow(): void
    {
        $this->seed(WorkflowsCapabilitiesSeeder::class);

        [$tenantId, $agentId, $token] = $this->registerAgentWithPermissions([
            'workflow.definitions.manage', 'workflow.definitions.read',
        ]);

        // A Workflow watching for cart_abandoned events — owner_id > 0
        // matches any real Agent-owned cart.
        $create = $this->postJson('/mcp/v1/execute', [
            'capability' => 'workflow.definition.create',
            'input' => [
                'name' => 'Abandoned Cart Follow-up',
                'event_type' => 'cart_abandoned',
                'rules' => [
                    ['condition_type' => 'greater_than', 'field' => 'owner_id', 'threshold_value' => 0],
                ],
                'actions' => [
                    ['action_type' => 'notify_agent', 'parameters' => ['message' => 'Cart {cart_id} was abandoned']],
                ],
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $create->assertStatus(200);
        $workflowId = $create->json('data.workflow.id');

        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantId, $product->id, 10));
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $product->id, 1);

        // Simulate the Cart having been idle for 30 hours.
        DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(30)]);

        // The real scheduled command — no manual Event::dispatch() anywhere
        // in this test.
        $this->artisan('commerce:check-abandoned-carts')->assertExitCode(0);

        $logs = $this->postJson('/mcp/v1/execute', [
            'capability' => 'workflow.log.list',
            'input' => ['workflow_id' => $workflowId],
        ], ['Authorization' => "Bearer {$token}"]);

        $logs->assertStatus(200);
        $logRows = $logs->json('data.logs');
        $this->assertCount(1, $logRows);
        $this->assertSame('success', $logRows[0]['status']);
        $this->assertSame($cart->id, $logRows[0]['eventData']['cart_id']);
        $this->assertSame("Cart {$cart->id} was abandoned", $logRows[0]['actionsExecuted'][0]['result']['message']);
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Ops Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Ops Operator', 'ops-operator-'.uniqid());

            foreach ($permissionKeys as $permissionKey) {
                $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
                $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
                app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            }

            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $agent->id, $token];
    }
}
