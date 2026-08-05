<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AgentProfileTest extends TestCase
{
    public function test_fromConfig_buildsAProfileFromAValidArray(): void
    {
        $profile = $this->validProfile();

        $this->assertSame(AgentType::Ceo, $profile->type);
        $this->assertSame('CEO Agent', $profile->name);
        $this->assertSame(['reporting.sales.read'], $profile->permissions);
    }

    public function test_fromConfig_rejectsAConfigMissingARequiredKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("missing required key [description]");

        AgentProfile::fromConfig(AgentType::Ceo, [
            'name' => 'CEO Agent',
            'planning_rules' => ['default' => ['report.sales.generate']],
            'default_inputs' => [],
            'permissions' => [],
        ]);
    }

    public function test_fromConfig_rejectsPlanningRulesWithNoDefaultRule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("missing a required 'default' planning rule");

        AgentProfile::fromConfig(AgentType::Ceo, [
            'name' => 'CEO Agent',
            'description' => 'Test',
            'planning_rules' => ['sales' => ['report.sales.generate']],
            'default_inputs' => [],
            'permissions' => [],
        ]);
    }

    public function test_getCapabilitiesForGoal_matchesTheFirstKeywordFoundInGoalText(): void
    {
        $profile = $this->validProfile();

        $this->assertSame(
            ['report.sales.generate', 'commerce.coupon.create'],
            $profile->getCapabilitiesForGoal('Increase sales this week'),
        );
    }

    public function test_getCapabilitiesForGoal_isCaseInsensitive(): void
    {
        $profile = $this->validProfile();

        $this->assertSame(
            ['report.sales.generate', 'commerce.coupon.create'],
            $profile->getCapabilitiesForGoal('INCREASE SALES THIS WEEK'),
        );
    }

    public function test_getCapabilitiesForGoal_fallsBackToDefaultWhenNothingMatches(): void
    {
        $profile = $this->validProfile();

        $this->assertSame(['report.sales.generate'], $profile->getCapabilitiesForGoal('Water the plants'));
    }

    public function test_getDefaultInput_returnsTheRawConfiguredInputForACapability(): void
    {
        $profile = $this->validProfile();

        $this->assertSame(
            ['code' => '{coupon_code}', 'discount_type' => 'percentage'],
            $profile->getDefaultInput('commerce.coupon.create'),
        );
    }

    public function test_getDefaultInput_returnsEmptyArrayForAnUnconfiguredCapability(): void
    {
        $profile = $this->validProfile();

        $this->assertSame([], $profile->getDefaultInput('notification.message.send'));
    }

    private function validProfile(): AgentProfile
    {
        return AgentProfile::fromConfig(AgentType::Ceo, [
            'name' => 'CEO Agent',
            'description' => 'Test profile',
            'planning_rules' => [
                'sales' => ['report.sales.generate', 'commerce.coupon.create'],
                'default' => ['report.sales.generate'],
            ],
            'default_inputs' => [
                'commerce.coupon.create' => ['code' => '{coupon_code}', 'discount_type' => 'percentage'],
            ],
            'permissions' => ['reporting.sales.read'],
        ]);
    }
}
