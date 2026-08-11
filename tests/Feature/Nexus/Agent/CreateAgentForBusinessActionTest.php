<?php

namespace Tests\Feature\Nexus\Agent;

use App\Domains\Nexus\Agent\Application\Actions\CreateAgentForBusinessAction;
use App\Domains\Nexus\Agent\Domain\Events\AgentWasCreatedForBusiness;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Proves CreateAgentForBusinessAction really provisions a Core Agent +
 * AgentToken (RegisterAgentAction/GenerateAgentTokenAction), not a mock —
 * the whole point of "extend, don't rebuild."
 */
class CreateAgentForBusinessActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_createsNexusAgentAndRealCoreAgentWithToken(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        Event::fake();
        $result = app(CreateAgentForBusinessAction::class)->execute(
            businessId: $business->id,
            tenantId: $business->tenantId,
            organizationId: $business->organizationId,
            nameFa: 'ایجنت آزمایشی',
            nameEn: 'Test Agent',
        );

        $this->assertNotNull($result->id);
        $this->assertNotNull($result->coreAgentId);
        $this->assertNotNull($result->plainCoreAgentToken);
        $this->assertStringStartsWith('oc_agent_', $result->plainCoreAgentToken);

        $this->assertDatabaseHas('nexus_agents', [
            'id' => $result->id,
            'business_id' => $business->id,
            'core_agent_id' => $result->coreAgentId,
            'name_en' => 'Test Agent',
        ]);
        $this->assertDatabaseHas('agents', [
            'id' => $result->coreAgentId,
            'tenant_id' => $business->tenantId,
            'organization_id' => $business->organizationId,
            'type' => 'custom',
        ]);
        $this->assertDatabaseHas('agent_tokens', ['agent_id' => $result->coreAgentId]);

        Event::assertDispatched(AgentWasCreatedForBusiness::class);
    }
}
