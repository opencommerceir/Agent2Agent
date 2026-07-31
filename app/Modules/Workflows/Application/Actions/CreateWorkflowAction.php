<?php

namespace App\Modules\Workflows\Application\Actions;

use App\Modules\Workflows\Application\DTOs\WorkflowData;
use App\Modules\Workflows\Domain\Entities\Workflow;
use App\Modules\Workflows\Domain\Entities\WorkflowAction as WorkflowActionEntity;
use App\Modules\Workflows\Domain\Entities\WorkflowRule;
use App\Modules\Workflows\Domain\Exceptions\InvalidWorkflowException;
use App\Modules\Workflows\Domain\Repositories\WorkflowRepositoryInterface;
use App\Modules\Workflows\Domain\ValueObjects\EventType;
use App\Modules\Workflows\Domain\ValueObjects\Threshold;

/**
 * One Action = one business operation: create a Workflow with its rules
 * and actions frozen at creation (Workflow's own docblock). Requires at
 * least one rule and one action — a Workflow that can never match, or
 * that matches but does nothing, isn't a meaningful automation
 * (InvalidWorkflowException; not requested explicitly but the same
 * "reject a structurally meaningless definition" reasoning
 * CreateCategoryAction/CreateTagAction already apply to duplicate
 * names).
 */
final class CreateWorkflowAction
{
    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
    ) {
    }

    /**
     * @param list<array{condition_type: string, field: string, threshold_value: int}> $rules
     * @param list<array{action_type: string, parameters?: array<string, mixed>}> $actions
     */
    public function execute(
        int $tenantId,
        string $name,
        ?string $description,
        string $eventType,
        array $rules,
        array $actions,
    ): WorkflowData {
        if ($rules === []) {
            throw new InvalidWorkflowException('A Workflow needs at least one rule.');
        }

        if ($actions === []) {
            throw new InvalidWorkflowException('A Workflow needs at least one action.');
        }

        $ruleEntities = array_map(
            fn (array $rule) => WorkflowRule::create(
                $rule['condition_type'],
                $rule['field'],
                new Threshold((int) $rule['threshold_value']),
            ),
            $rules,
        );

        $actionEntities = array_map(
            fn (array $action) => WorkflowActionEntity::create(
                $action['action_type'],
                $action['parameters'] ?? [],
            ),
            $actions,
        );

        $workflow = Workflow::create(
            tenantId: $tenantId,
            name: $name,
            description: $description,
            eventType: EventType::from($eventType),
            rules: $ruleEntities,
            actions: $actionEntities,
        );

        $workflow = $this->workflows->save($workflow);

        return WorkflowData::fromEntity($workflow);
    }
}
