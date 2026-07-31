<?php

namespace App\Modules\Workflows\Domain\Entities;

/**
 * One thing a Workflow does once its rules match. No `id`/`workflowId`
 * property on the Domain Entity — same reasoning WorkflowRule's own
 * docblock gives. actionType is a plain string for the same reason
 * WorkflowRule's conditionType is — ExecuteWorkflowActionAction is the
 * single place that knows the finite set it currently understands
 * (only `notify_agent` this stage).
 */
final class WorkflowAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    private function __construct(
        private readonly string $actionType,
        private readonly array $parameters,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function create(string $actionType, array $parameters): self
    {
        return new self($actionType, $parameters);
    }

    public function actionType(): string
    {
        return $this->actionType;
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
