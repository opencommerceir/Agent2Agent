<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\DeterministicPlanner;
use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use PHPUnit\Framework\TestCase;

/**
 * Builds AgentProfile directly via fromConfig() with an inline config
 * array — no Laravel config()/container needed, keeping this a pure,
 * framework-free Unit test the same as every other test in this
 * directory, even though real profiles ship as config/agents/*.php files
 * (see ConfigBasedAgentProfileRepositoryTest, a Feature test, for that
 * loading path).
 */
class DeterministicPlannerTest extends TestCase
{
    public function test_createPlan_matchesTheFirstRuleWhoseKeywordAppearsInTheGoalText(): void
    {
        $planner = new DeterministicPlanner();
        $profile = $this->ceoLikeProfile();
        $goal = Goal::fromText('Increase sales by 15% this week', AgentType::Ceo);

        $plan = $planner->createPlan($goal, $profile);

        $this->assertSame([
            'report.sales.generate',
            'analytics.kpi.calculate',
            'commerce.coupon.create',
            'notification.message.send',
        ], array_map(fn ($step) => $step->capability, $plan->steps));
    }

    public function test_createPlan_fallsBackToTheDefaultRuleWhenNoKeywordMatches(): void
    {
        $planner = new DeterministicPlanner();
        $profile = $this->ceoLikeProfile();
        $goal = Goal::fromText('Water the office plants', AgentType::Ceo);

        $plan = $planner->createPlan($goal, $profile);

        $this->assertFalse($plan->isEmpty());
        $this->assertSame('report.sales.generate', $plan->steps[0]->capability);
    }

    public function test_createPlan_resolvesDateTokensToRealYmdStrings(): void
    {
        $planner = new DeterministicPlanner();
        $profile = $this->ceoLikeProfile();
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);

        $plan = $planner->createPlan($goal, $profile);
        $salesStep = $plan->steps[0];

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $salesStep->input['start_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $salesStep->input['end_date']);
        $this->assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $salesStep->input['end_date']);
    }

    public function test_createPlan_resolvesCouponCodeTokenToAValidFormat(): void
    {
        $planner = new DeterministicPlanner();
        $profile = $this->ceoLikeProfile();
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);

        $plan = $planner->createPlan($goal, $profile);
        $couponStep = $plan->steps[2];

        $this->assertSame('commerce.coupon.create', $couponStep->capability);
        $this->assertMatchesRegularExpression('/^COUPON-[A-Z0-9]{5}$/', $couponStep->input['code']);
    }

    public function test_createPlan_resolvesDiscountPercentTokenFromGoalText(): void
    {
        $planner = new DeterministicPlanner();
        $profile = $this->ceoLikeProfile();

        $goalWithPercent = Goal::fromText('Increase sales by 25% this week', AgentType::Ceo);
        $plan = $planner->createPlan($goalWithPercent, $profile);
        $this->assertSame(25, $plan->steps[2]->input['discount_value']);

        $goalWithoutPercent = Goal::fromText('Grow sales this month', AgentType::Ceo);
        $plan = $planner->createPlan($goalWithoutPercent, $profile);
        $this->assertSame(10, $plan->steps[2]->input['discount_value']);
    }

    public function test_createPlan_leavesNonTokenValuesUntouched(): void
    {
        $planner = new DeterministicPlanner();
        $profile = $this->ceoLikeProfile();
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);

        $plan = $planner->createPlan($goal, $profile);
        $couponStep = $plan->steps[2];

        $this->assertSame('percentage', $couponStep->input['discount_type']);
    }

    public function test_createPlan_differentProfilesProduceDifferentPlansForTheSameGoalText(): void
    {
        $planner = new DeterministicPlanner();
        $goal = Goal::fromText('Create summer promotion', AgentType::Sales);

        $ceoPlan = $planner->createPlan($goal, $this->ceoLikeProfile());
        $salesPlan = $planner->createPlan($goal, $this->salesLikeProfile());

        // CEO's profile has no "promotion" rule -> falls to its own default.
        $this->assertSame('report.sales.generate', $ceoPlan->steps[0]->capability);
        // Sales' profile has a dedicated "promotion" rule.
        $this->assertSame('commerce.coupon.create', $salesPlan->steps[0]->capability);
    }

    private function ceoLikeProfile(): AgentProfile
    {
        return AgentProfile::fromConfig(AgentType::Ceo, [
            'name' => 'CEO Agent',
            'description' => 'Test profile',
            'planning_rules' => [
                'sales' => ['report.sales.generate', 'analytics.kpi.calculate', 'commerce.coupon.create', 'notification.message.send'],
                'default' => ['report.sales.generate'],
            ],
            'default_inputs' => [
                'report.sales.generate' => ['start_date' => '{date:-7}', 'end_date' => '{date:0}'],
                'analytics.kpi.calculate' => ['kpi_type' => 'revenue', 'time_period' => 'weekly'],
                'commerce.coupon.create' => ['code' => '{coupon_code}', 'discount_type' => 'percentage', 'discount_value' => '{discount_percent}'],
                'notification.message.send' => ['type' => 'promotion_announcement', 'channel' => 'email', 'recipient' => 'marketing@opencommerce.local'],
            ],
            'permissions' => ['reporting.sales.read'],
        ]);
    }

    private function salesLikeProfile(): AgentProfile
    {
        return AgentProfile::fromConfig(AgentType::Sales, [
            'name' => 'Sales Agent',
            'description' => 'Test profile',
            'planning_rules' => [
                'promotion' => ['commerce.coupon.create'],
                'default' => ['report.sales.generate'],
            ],
            'default_inputs' => [
                'commerce.coupon.create' => ['code' => '{coupon_code}', 'discount_type' => 'percentage', 'discount_value' => 10],
            ],
            'permissions' => ['commerce.coupons.create'],
        ]);
    }
}
