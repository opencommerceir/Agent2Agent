<?php

namespace Tests\Unit\Workflows;

use App\Modules\Workflows\Domain\Entities\Workflow;
use App\Modules\Workflows\Domain\Entities\WorkflowAction;
use App\Modules\Workflows\Domain\Entities\WorkflowRule;
use App\Modules\Workflows\Domain\ValueObjects\EventType;
use App\Modules\Workflows\Domain\ValueObjects\Threshold;
use App\Modules\Workflows\Domain\ValueObjects\WorkflowStatus;
use PHPUnit\Framework\TestCase;

class WorkflowTest extends TestCase
{
    private function makeWorkflow(): Workflow
    {
        return Workflow::create(
            tenantId: 1,
            name: 'Low Stock Alert',
            description: 'Notify when stock is low',
            eventType: EventType::InventoryLow,
            rules: [WorkflowRule::create('less_than', 'quantity_on_hand', new Threshold(5))],
            actions: [WorkflowAction::create('notify_agent', ['message' => 'Product {name} is low on stock'])],
        );
    }

    public function test_create_startsActiveWithGivenRulesAndActions(): void
    {
        $workflow = $this->makeWorkflow();

        $this->assertSame(WorkflowStatus::Active, $workflow->status());
        $this->assertTrue($workflow->isActive());
        $this->assertCount(1, $workflow->rules());
        $this->assertCount(1, $workflow->actions());
    }

    public function test_update_changesNameDescriptionAndStatusOnly(): void
    {
        $workflow = $this->makeWorkflow();

        $workflow->update('Renamed', 'New description', WorkflowStatus::Paused);

        $this->assertSame('Renamed', $workflow->name());
        $this->assertSame('New description', $workflow->description());
        $this->assertSame(WorkflowStatus::Paused, $workflow->status());
        $this->assertFalse($workflow->isActive());
        $this->assertCount(1, $workflow->rules()); // unchanged
    }
}
