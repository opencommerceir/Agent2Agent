<?php

namespace App\Modules\Workflows\Application\Actions;

use App\Modules\Workflows\Domain\Entities\Workflow;
use App\Modules\Workflows\Domain\Entities\WorkflowAction;
use App\Modules\Workflows\Domain\Events\WorkflowActionExecuted;
use App\Modules\Workflows\Domain\Exceptions\InvalidWorkflowException;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Runs one WorkflowAction and reports what happened — never lets a
 * failure propagate out of TriggerWorkflowAction's loop over a
 * Workflow's actions, so one bad action can't prevent the rest of that
 * Workflow's actions (or a later Workflow entirely) from running; the
 * failure is recorded into the caller's WorkflowLog instead (same "keep
 * going, report per-item failures" reasoning
 * SyncWooCommerceProductsAction already established for a bulk
 * operation).
 *
 * `notify_agent` is the only actionType implemented this stage (the Low
 * Stock Alert scenario) — and it does not yet deliver anywhere. No
 * Notification/Inbox system exists in Core yet, so "notifying" currently
 * means: render the message template (replacing `{field}` placeholders
 * with the triggering event's data) and record the rendered string in
 * the WorkflowLog. A future stage wiring real delivery (email, Slack, an
 * MCP push channel) would extend this Action's `notify_agent` branch,
 * not replace it — the templating logic stays the same either way.
 */
final class ExecuteWorkflowActionAction
{
    /**
     * @param array<string, mixed> $eventData
     * @return array<string, mixed>
     */
    public function execute(Workflow $workflow, WorkflowAction $action, array $eventData): array
    {
        try {
            $result = match ($action->actionType()) {
                'notify_agent' => $this->notifyAgent($action, $eventData),
                default => throw new InvalidWorkflowException(
                    "Unknown workflow action type [{$action->actionType()}]."
                ),
            };

            Event::dispatch(new WorkflowActionExecuted($workflow, $action, $result));

            return ['actionType' => $action->actionType(), 'success' => true, 'result' => $result];
        } catch (Throwable $e) {
            return ['actionType' => $action->actionType(), 'success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $eventData
     * @return array<string, mixed>
     */
    private function notifyAgent(WorkflowAction $action, array $eventData): array
    {
        $message = (string) ($action->parameters()['message'] ?? '');

        foreach ($eventData as $field => $value) {
            $message = str_replace('{'.$field.'}', (string) $value, $message);
        }

        return ['message' => $message];
    }
}
