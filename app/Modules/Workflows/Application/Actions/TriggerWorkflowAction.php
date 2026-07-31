<?php

namespace App\Modules\Workflows\Application\Actions;

use App\Modules\Workflows\Application\DTOs\WorkflowData;
use App\Modules\Workflows\Domain\Entities\Workflow;
use App\Modules\Workflows\Domain\Entities\WorkflowLog;
use App\Modules\Workflows\Domain\Events\WorkflowWasTriggered;
use App\Modules\Workflows\Domain\Repositories\WorkflowRepositoryInterface;
use App\Modules\Workflows\Domain\Services\WorkflowEvaluator;
use App\Modules\Workflows\Domain\ValueObjects\EventType;
use Illuminate\Support\Facades\Event;

/**
 * The single place an external happening becomes zero or more executed
 * Workflows — used identically by the `workflow.event.trigger` MCP
 * capability (an Agent manually raises an event) and by real Domain
 * Event Listeners (`InventoryLowListener` translates Commerce's
 * `InventoryWasCommitted` into a call here). Actions composing Actions,
 * not a special case (HANDOFF §3.3 — Commerce's ProcessPaymentAction
 * depending on PlaceOrderAction is the same idea).
 *
 * Every matching Workflow gets its own WorkflowLog row, whether every
 * one of its actions succeeded or not — a Workflow that fired but had
 * one failing action is still a fact worth recording, not silently
 * dropped.
 */
final class TriggerWorkflowAction
{
    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
        private readonly WorkflowEvaluator $evaluator,
        private readonly ExecuteWorkflowActionAction $executeAction,
    ) {
    }

    /**
     * @param array<string, mixed> $eventData
     * @return array{triggered_count: int, workflows: list<array<string, mixed>>}
     */
    public function execute(int $tenantId, string $eventType, array $eventData): array
    {
        $type = EventType::from($eventType);
        $candidates = $this->workflows->findActiveByEventType($type, $tenantId);

        $triggeredCount = 0;
        $triggered = [];

        foreach ($candidates as $workflow) {
            if (! $this->evaluator->evaluate($workflow, $eventData)) {
                continue;
            }

            Event::dispatch(new WorkflowWasTriggered($workflow, $eventData));

            $actionsExecuted = array_map(
                fn ($action) => $this->executeAction->execute($workflow, $action, $eventData),
                $workflow->actions(),
            );

            $allSucceeded = ! in_array(false, array_column($actionsExecuted, 'success'), true);

            $log = WorkflowLog::create(
                tenantId: $tenantId,
                workflowId: $workflow->id(),
                eventData: $eventData,
                actionsExecuted: $actionsExecuted,
                status: $allSucceeded ? 'success' : 'failed',
            );
            $this->workflows->saveLog($log);

            $triggeredCount++;
            $triggered[] = WorkflowData::fromEntity($workflow)->toArray();
        }

        return ['triggered_count' => $triggeredCount, 'workflows' => $triggered];
    }
}
