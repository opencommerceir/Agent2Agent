<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Core\Application\DTOs\CapabilityData;
use App\Modules\AgentOrchestrator\Application\Prompts\PlanningPromptTemplate;
use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use PHPUnit\Framework\TestCase;

class PromptTemplateTest extends TestCase
{
    public function test_forGoal_includesTheGoalTextAndPersonaDetails(): void
    {
        $prompt = PlanningPromptTemplate::forGoal($this->goal(), $this->profile(), []);

        $this->assertStringContainsString('Increase sales by 15% this week', $prompt);
        $this->assertStringContainsString('CEO Agent', $prompt);
        $this->assertStringContainsString('ceo', $prompt);
        $this->assertStringContainsString('Strategic decision-making agent', $prompt);
    }

    public function test_forGoal_listsEveryCapabilityNameAndInputSchema(): void
    {
        $capabilities = [
            new CapabilityData(1, 'commerce.coupon.create', 'Create a discount Coupon', ['code' => 'string'], ['coupon' => 'array'], ['commerce.coupons.create']),
            new CapabilityData(2, 'report.sales.generate', 'Generate a Sales Report', ['start_date' => 'date'], ['report' => 'array'], ['reporting.sales.read']),
        ];

        $prompt = PlanningPromptTemplate::forGoal($this->goal(), $this->profile(), $capabilities);

        $this->assertStringContainsString('commerce.coupon.create', $prompt);
        $this->assertStringContainsString('Create a discount Coupon', $prompt);
        $this->assertStringContainsString('report.sales.generate', $prompt);
        $this->assertStringContainsString('"code":"string"', $prompt);
    }

    public function test_forGoal_includesThePersonasOwnPermissionsAsAHint(): void
    {
        $prompt = PlanningPromptTemplate::forGoal($this->goal(), $this->profile(), []);

        $this->assertStringContainsString('reporting.sales.read', $prompt);
    }

    public function test_forGoal_instructsAgainstInventingCapabilityNames(): void
    {
        $prompt = PlanningPromptTemplate::forGoal($this->goal(), $this->profile(), []);

        $this->assertStringContainsString('ONLY the capabilities listed above', $prompt);
        $this->assertStringContainsString("Do not invent a capability name", $prompt);
    }

    private function goal(): Goal
    {
        return Goal::fromText('Increase sales by 15% this week', AgentType::Ceo);
    }

    private function profile(): AgentProfile
    {
        return AgentProfile::fromConfig(AgentType::Ceo, [
            'name' => 'CEO Agent',
            'description' => 'Strategic decision-making agent for business oversight.',
            'planning_rules' => ['default' => []],
            'default_inputs' => [],
            'permissions' => ['reporting.sales.read'],
        ]);
    }
}
