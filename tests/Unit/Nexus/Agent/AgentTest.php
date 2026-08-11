<?php

namespace Tests\Unit\Nexus\Agent;

use App\Domains\Nexus\Agent\Domain\Entities\Agent;
use PHPUnit\Framework\TestCase;

class AgentTest extends TestCase
{
    public function test_create_withMinimalData_hasNullOptionalFields(): void
    {
        $agent = Agent::create(businessId: 1, nameFa: 'ایجنت آزمایشی', nameEn: 'Test Agent');

        $this->assertNull($agent->id());
        $this->assertSame(1, $agent->businessId());
        $this->assertNull($agent->coreAgentId());
        $this->assertSame('ایجنت آزمایشی', $agent->nameFa());
        $this->assertSame('Test Agent', $agent->nameEn());
        $this->assertNull($agent->personality());
        $this->assertNull($agent->tone());
        $this->assertNull($agent->authorityLimits());
        $this->assertNull($agent->strategies());
    }

    public function test_attachCoreAgent_setsCoreAgentId(): void
    {
        $agent = Agent::create(1, 'ایجنت آزمایشی', 'Test Agent');

        $agent->attachCoreAgent(42);

        $this->assertSame(42, $agent->coreAgentId());
    }

    public function test_updatePersonality_setsPersonalityAndTone(): void
    {
        $agent = Agent::create(1, 'ایجنت آزمایشی', 'Test Agent');

        $agent->updatePersonality('Assertive but fair negotiator', 'formal');

        $this->assertSame('Assertive but fair negotiator', $agent->personality());
        $this->assertSame('formal', $agent->tone());
    }

    public function test_setAuthorityLimits_setsLimits(): void
    {
        $agent = Agent::create(1, 'ایجنت آزمایشی', 'Test Agent');

        $agent->setAuthorityLimits(['max_deal_value' => 1000000, 'max_discount_percent' => 10]);

        $this->assertSame(['max_deal_value' => 1000000, 'max_discount_percent' => 10], $agent->authorityLimits());
    }

    public function test_setStrategies_setsStrategies(): void
    {
        $agent = Agent::create(1, 'ایجنت آزمایشی', 'Test Agent');

        $agent->setStrategies(['aggressive', 'volume_discount']);

        $this->assertSame(['aggressive', 'volume_discount'], $agent->strategies());
    }
}
