<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\DeterministicPlanner;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use PHPUnit\Framework\TestCase;

class DeterministicPlannerTest extends TestCase
{
    public function test_createPlan_salesGoal_produces5RealSteps(): void
    {
        $planner = new DeterministicPlanner();
        $goal = Goal::fromText('Increase sales by 15% this week', AgentType::Ceo);

        $plan = $planner->createPlan($goal);

        $capabilities = array_map(fn ($step) => $step->capability, $plan->steps);

        $this->assertCount(5, $plan->steps);
        $this->assertSame([
            'report.sales.generate',
            'analytics.kpi.calculate',
            'analytics.kpi.calculate',
            'commerce.coupon.create',
            'notification.message.send',
        ], $capabilities);
    }

    public function test_createPlan_salesGoal_parsesDiscountPercentFromGoalText(): void
    {
        $planner = new DeterministicPlanner();
        $goal = Goal::fromText('Increase sales by 25% this week', AgentType::Ceo);

        $plan = $planner->createPlan($goal);
        $couponStep = $plan->steps[3];

        $this->assertSame('commerce.coupon.create', $couponStep->capability);
        $this->assertSame(25, $couponStep->input['discount_value']);
    }

    public function test_createPlan_salesGoal_defaultsDiscountPercentWhenNotStated(): void
    {
        $planner = new DeterministicPlanner();
        $goal = Goal::fromText('Grow sales this month', AgentType::Ceo);

        $plan = $planner->createPlan($goal);
        $couponStep = $plan->steps[3];

        $this->assertSame(10, $couponStep->input['discount_value']);
    }

    public function test_createPlan_salesGoal_generatesAValidCouponCode(): void
    {
        $planner = new DeterministicPlanner();
        $goal = Goal::fromText('Increase sales', AgentType::Sales);

        $plan = $planner->createPlan($goal);
        $code = $plan->steps[3]->input['code'];

        $this->assertMatchesRegularExpression('/^COUPON-[A-Z0-9]{5}$/', $code);
    }

    public function test_createPlan_supportGoal_producesOneRealStep(): void
    {
        $planner = new DeterministicPlanner();
        $goal = Goal::fromText('Review open support tickets', AgentType::Support);

        $plan = $planner->createPlan($goal);

        $this->assertCount(1, $plan->steps);
        $this->assertSame('crm.ticket.list', $plan->steps[0]->capability);
        $this->assertSame('open', $plan->steps[0]->input['status']);
    }

    public function test_createPlan_financeGoal_producesTwoRealSteps(): void
    {
        $planner = new DeterministicPlanner();
        $goal = Goal::fromText('Review finance and revenue this month', AgentType::Finance);

        $plan = $planner->createPlan($goal);

        $this->assertCount(2, $plan->steps);
        $this->assertSame('report.revenue.generate', $plan->steps[0]->capability);
        $this->assertSame('finance.invoice.list', $plan->steps[1]->capability);
    }

    public function test_createPlan_unrecognizedGoal_producesAnEmptyPlan(): void
    {
        $planner = new DeterministicPlanner();
        $goal = Goal::fromText('Water the office plants', AgentType::Ceo);

        $plan = $planner->createPlan($goal);

        $this->assertTrue($plan->isEmpty());
    }

    public function test_createPlan_everyStepInputSatisfiesTheRealCapabilitySchemas(): void
    {
        // A regression guard for exactly the bug class this module's own
        // docblock calls out: an empty `input` array would fail
        // MCPRequestValidationService for every one of these capabilities.
        $planner = new DeterministicPlanner();
        $goal = Goal::fromText('Increase sales by 15% this week', AgentType::Ceo);

        $plan = $planner->createPlan($goal);

        $this->assertArrayHasKey('start_date', $plan->steps[0]->input);
        $this->assertArrayHasKey('end_date', $plan->steps[0]->input);
        $this->assertArrayHasKey('kpi_type', $plan->steps[1]->input);
        $this->assertArrayHasKey('kpi_type', $plan->steps[2]->input);
        $this->assertArrayHasKey('code', $plan->steps[3]->input);
        $this->assertIsInt($plan->steps[3]->input['discount_value']);
        $this->assertArrayHasKey('type', $plan->steps[4]->input);
        $this->assertArrayHasKey('recipient', $plan->steps[4]->input);
        $this->assertArrayHasKey('channel', $plan->steps[4]->input);
    }
}
