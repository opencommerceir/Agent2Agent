<?php

namespace App\Modules\Workflows\Domain\Events;

use App\Modules\Workflows\Domain\Entities\Workflow;

/**
 * Domain event: a fact that already happened. Dispatched after a
 * Workflow's rules matched an incoming event's data — before any of its
 * actions have executed (see WorkflowActionExecuted for that).
 */
final class WorkflowWasTriggered
{
    /**
     * @param array<string, mixed> $eventData
     */
    public function __construct(
        public readonly Workflow $workflow,
        public readonly array $eventData,
    ) {
    }
}
