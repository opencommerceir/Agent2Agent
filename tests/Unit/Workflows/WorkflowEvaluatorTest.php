<?php

namespace Tests\Unit\Workflows;

use App\Modules\Workflows\Domain\Entities\Workflow;
use App\Modules\Workflows\Domain\Entities\WorkflowAction;
use App\Modules\Workflows\Domain\Entities\WorkflowRule;
use App\Modules\Workflows\Domain\Services\WorkflowEvaluator;
use App\Modules\Workflows\Domain\ValueObjects\EventType;
use App\Modules\Workflows\Domain\ValueObjects\Threshold;
use App\Modules\Workflows\Domain\ValueObjects\WorkflowStatus;
use PHPUnit\Framework\TestCase;

class WorkflowEvaluatorTest extends TestCase
{
    private WorkflowEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new WorkflowEvaluator();
    }

    public function test_matches_lessThan_withValueBelowThreshold_returnsTrue(): void
    {
        $rule = WorkflowRule::create('less_than', 'quantity_on_hand', new Threshold(5));

        $this->assertTrue($this->evaluator->matches($rule, ['quantity_on_hand' => 3]));
    }

    public function test_matches_lessThan_withValueAtOrAboveThreshold_returnsFalse(): void
    {
        $rule = WorkflowRule::create('less_than', 'quantity_on_hand', new Threshold(5));

        $this->assertFalse($this->evaluator->matches($rule, ['quantity_on_hand' => 5]));
        $this->assertFalse($this->evaluator->matches($rule, ['quantity_on_hand' => 10]));
    }

    public function test_matches_greaterThan_withValueAboveThreshold_returnsTrue(): void
    {
        $rule = WorkflowRule::create('greater_than', 'total_amount', new Threshold(100000));

        $this->assertTrue($this->evaluator->matches($rule, ['total_amount' => 150000]));
    }

    public function test_matches_equals_withMatchingValue_returnsTrue(): void
    {
        $rule = WorkflowRule::create('equals', 'quantity_on_hand', new Threshold(0));

        $this->assertTrue($this->evaluator->matches($rule, ['quantity_on_hand' => 0]));
    }

    public function test_matches_withMissingField_returnsFalse(): void
    {
        $rule = WorkflowRule::create('less_than', 'quantity_on_hand', new Threshold(5));

        $this->assertFalse($this->evaluator->matches($rule, ['other_field' => 1]));
    }

    public function test_matches_withUnknownConditionType_returnsFalse(): void
    {
        $rule = WorkflowRule::create('unknown_condition', 'quantity_on_hand', new Threshold(5));

        $this->assertFalse($this->evaluator->matches($rule, ['quantity_on_hand' => 1]));
    }

    public function test_evaluate_withAllRulesMatching_returnsTrue(): void
    {
        $workflow = Workflow::create(
            1, 'Low Stock', null, EventType::InventoryLow,
            [WorkflowRule::create('less_than', 'quantity_on_hand', new Threshold(5))],
            [WorkflowAction::create('notify_agent', [])],
        );

        $this->assertTrue($this->evaluator->evaluate($workflow, ['quantity_on_hand' => 3]));
    }

    public function test_evaluate_withOneRuleFailing_returnsFalse(): void
    {
        $workflow = Workflow::create(
            1, 'Low Stock', null, EventType::InventoryLow,
            [
                WorkflowRule::create('less_than', 'quantity_on_hand', new Threshold(5)),
                WorkflowRule::create('equals', 'category', new Threshold(1)),
            ],
            [WorkflowAction::create('notify_agent', [])],
        );

        // quantity_on_hand matches, but category is missing from event data.
        $this->assertFalse($this->evaluator->evaluate($workflow, ['quantity_on_hand' => 3]));
    }

    public function test_evaluate_withInactiveWorkflow_returnsFalseEvenIfRulesMatch(): void
    {
        $workflow = Workflow::create(
            1, 'Low Stock', null, EventType::InventoryLow,
            [WorkflowRule::create('less_than', 'quantity_on_hand', new Threshold(5))],
            [WorkflowAction::create('notify_agent', [])],
        );
        $workflow->update($workflow->name(), $workflow->description(), WorkflowStatus::Paused);

        $this->assertFalse($this->evaluator->evaluate($workflow, ['quantity_on_hand' => 3]));
    }
}
