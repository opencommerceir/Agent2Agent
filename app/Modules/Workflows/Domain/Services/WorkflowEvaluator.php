<?php

namespace App\Modules\Workflows\Domain\Services;

use App\Modules\Workflows\Domain\Entities\Workflow;
use App\Modules\Workflows\Domain\Entities\WorkflowRule;

/**
 * The single place that knows how to check a WorkflowRule against
 * incoming event data — pure and framework-free, the same shape
 * Commerce's PricingService/CouponValidationService and Finance's
 * TaxCalculationService already establish: no Repository dependency, no
 * knowledge of *which* Workflow applies (TriggerWorkflowAction's job),
 * only how to answer "does this rule match this data".
 *
 * A Workflow's rules are AND-combined — every rule must match for the
 * Workflow to trigger, not just one. A Workflow with zero rules never
 * matches (an empty rule set is not "always true"; CreateWorkflowAction
 * already rejects creating one, but evaluate() guards against it too, in
 * case a Workflow is ever loaded from a row that predates that check).
 */
final class WorkflowEvaluator
{
    /**
     * @param array<string, mixed> $eventData
     */
    public function matches(WorkflowRule $rule, array $eventData): bool
    {
        if (! array_key_exists($rule->field(), $eventData)) {
            return false;
        }

        $actual = $eventData[$rule->field()];
        $threshold = $rule->threshold()->value();

        return match ($rule->conditionType()) {
            'less_than' => $actual < $threshold,
            'greater_than' => $actual > $threshold,
            'equals' => $actual == $threshold,
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $eventData
     */
    public function evaluate(Workflow $workflow, array $eventData): bool
    {
        if (! $workflow->isActive() || $workflow->rules() === []) {
            return false;
        }

        foreach ($workflow->rules() as $rule) {
            if (! $this->matches($rule, $eventData)) {
                return false;
            }
        }

        return true;
    }
}
