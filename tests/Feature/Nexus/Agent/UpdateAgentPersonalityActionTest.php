<?php

namespace Tests\Feature\Nexus\Agent;

use App\Domains\Nexus\Agent\Application\Actions\CreateAgentForBusinessAction;
use App\Domains\Nexus\Agent\Application\Actions\SetAuthorityLimitsAction;
use App\Domains\Nexus\Agent\Application\Actions\UpdateAgentPersonalityAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class UpdateAgentPersonalityActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAgent(): object
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        return app(CreateAgentForBusinessAction::class)->execute(
            $business->id,
            $business->tenantId,
            $business->organizationId,
            'ایجنت آزمایشی',
            'Test Agent',
        );
    }

    public function test_execute_updatesPersonalityAndTone(): void
    {
        $agent = $this->makeAgent();

        $result = app(UpdateAgentPersonalityAction::class)->execute($agent->id, 'Friendly and patient', 'casual');

        $this->assertSame('Friendly and patient', $result->personality);
        $this->assertSame('casual', $result->tone);
        $this->assertDatabaseHas('nexus_agents', ['id' => $agent->id, 'personality' => 'Friendly and patient', 'tone' => 'casual']);
    }

    public function test_execute_withNonExistentAgent_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(UpdateAgentPersonalityAction::class)->execute(9999, 'x', 'y');
    }

    public function test_setAuthorityLimits_updatesLimits(): void
    {
        $agent = $this->makeAgent();

        $result = app(SetAuthorityLimitsAction::class)->execute($agent->id, ['max_deal_value' => 5000000]);

        $this->assertSame(['max_deal_value' => 5000000], $result->authorityLimits);

        $persisted = app(\App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface::class)->findById($agent->id);
        $this->assertSame(['max_deal_value' => 5000000], $persisted->authorityLimits());
    }
}
