<?php

namespace Tests\Unit\Core;

use App\Core\Domain\Entities\Agent;
use App\Core\Domain\ValueObjects\AgentStatus;
use App\Core\Domain\ValueObjects\AgentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AgentTest extends TestCase
{
    public function test_register_withValidData_createsActiveAgent(): void
    {
        $agent = Agent::register(1, 1, 'Shopping Assistant', AgentType::Shopping);

        $this->assertNull($agent->id());
        $this->assertSame(1, $agent->tenantId());
        $this->assertSame('Shopping Assistant', $agent->name());
        $this->assertSame(AgentType::Shopping, $agent->type());
        $this->assertSame(AgentStatus::Active, $agent->status());
        $this->assertTrue($agent->isActive());
    }

    public function test_suspend_onActiveAgent_setsStatusToSuspended(): void
    {
        $agent = Agent::register(1, 1, 'Shopping Assistant', AgentType::Shopping);

        $agent->suspend();

        $this->assertSame(AgentStatus::Suspended, $agent->status());
        $this->assertFalse($agent->isActive());
    }

    public function test_deactivate_onActiveAgent_setsStatusToInactive(): void
    {
        $agent = Agent::register(1, 1, 'Shopping Assistant', AgentType::Shopping);

        $agent->deactivate();

        $this->assertSame(AgentStatus::Inactive, $agent->status());
        $this->assertFalse($agent->isActive());
    }

    public function test_activate_onSuspendedAgent_setsStatusBackToActive(): void
    {
        $agent = Agent::register(1, 1, 'Shopping Assistant', AgentType::Shopping);
        $agent->suspend();

        $agent->activate();

        $this->assertTrue($agent->isActive());
    }

    #[DataProvider('agentTypeProvider')]
    public function test_register_withEachAgentType_setsTypeCorrectly(AgentType $type): void
    {
        $agent = Agent::register(1, 1, 'Some Agent', $type);

        $this->assertSame($type, $agent->type());
    }

    public static function agentTypeProvider(): array
    {
        return [
            'shopping' => [AgentType::Shopping],
            'analytics' => [AgentType::Analytics],
            'customer_service' => [AgentType::CustomerService],
            'custom' => [AgentType::Custom],
        ];
    }
}
