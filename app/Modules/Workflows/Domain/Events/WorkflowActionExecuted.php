<?php

namespace App\Modules\Workflows\Domain\Events;

use App\Modules\Workflows\Domain\Entities\Workflow;
use App\Modules\Workflows\Domain\Entities\WorkflowAction;

/**
 * Domain event: a fact that already happened. Dispatched by
 * ExecuteWorkflowActionAction after one WorkflowAction has run
 * successfully (a failed action does not dispatch this — see that
 * Action's own docblock for why it catches and records the failure
 * instead of letting it propagate).
 */
final class WorkflowActionExecuted
{
    /**
     * @param array<string, mixed> $result
     */
    public function __construct(
        public readonly Workflow $workflow,
        public readonly WorkflowAction $action,
        public readonly array $result,
    ) {
    }
}
