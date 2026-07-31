<?php

namespace Tests\Unit\Workflows;

use App\Modules\Workflows\Domain\Entities\WorkflowLog;
use PHPUnit\Framework\TestCase;

class WorkflowLogTest extends TestCase
{
    public function test_create_setsAllFieldsAndNoId(): void
    {
        $log = WorkflowLog::create(
            tenantId: 1,
            workflowId: 2,
            eventData: ['quantity_on_hand' => 3],
            actionsExecuted: [['actionType' => 'notify_agent', 'success' => true]],
            status: 'success',
        );

        $this->assertNull($log->id());
        $this->assertSame(1, $log->tenantId());
        $this->assertSame(2, $log->workflowId());
        $this->assertSame(['quantity_on_hand' => 3], $log->eventData());
        $this->assertSame('success', $log->status());
    }
}
