<?php

namespace Tests\Feature\Workflows;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Workflows\Application\Actions\CreateWorkflowAction;
use App\Modules\Workflows\Application\Actions\UpdateWorkflowAction;
use App\Modules\Workflows\Domain\Exceptions\WorkflowNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UpdateWorkflowAction exercised directly — not wired to any MCP
 * capability this stage (see its own docblock).
 */
class UpdateWorkflowActionTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflow(int $tenantId)
    {
        return app(CreateWorkflowAction::class)->execute(
            tenantId: $tenantId,
            name: 'Low Stock Alert',
            description: null,
            eventType: 'inventory_low',
            rules: [['condition_type' => 'less_than', 'field' => 'quantity_on_hand', 'threshold_value' => 5]],
            actions: [['action_type' => 'notify_agent', 'parameters' => ['message' => 'low']]],
        );
    }

    public function test_execute_updatesNameDescriptionAndStatus(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $workflow = $this->createWorkflow($tenant->id);

        $result = app(UpdateWorkflowAction::class)->execute($workflow->id, $tenant->id, 'Renamed', 'New desc', 'paused');

        $this->assertSame('Renamed', $result->name);
        $this->assertSame('paused', $result->status);
        $this->assertDatabaseHas('workflows', ['id' => $workflow->id, 'name' => 'Renamed', 'status' => 'paused']);
    }

    public function test_execute_forNonexistentWorkflow_throwsWorkflowNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(WorkflowNotFoundException::class);

        app(UpdateWorkflowAction::class)->execute(999999, $tenant->id, 'X', null, 'active');
    }

    public function test_execute_forWorkflowInAnotherTenant_throwsWorkflowNotFoundException(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Globex Inc', 'globex-'.uniqid());
        $workflow = $this->createWorkflow($tenantA->id);

        $this->expectException(WorkflowNotFoundException::class);

        app(UpdateWorkflowAction::class)->execute($workflow->id, $tenantB->id, 'X', null, 'active');
    }
}
