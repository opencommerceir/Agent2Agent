<?php

namespace Tests\Feature\Nexus\Agent;

use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The real, non-faked event flow: VerifyBusinessAction dispatches
 * BusinessWasVerified -> CreateAgentOnBusinessVerifiedListener (registered
 * in NexusServiceProvider::boot()) -> CreateAgentForBusinessAction — no
 * direct call between the two domains, only an event in between.
 */
class CreateAgentOnBusinessVerifiedListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifyingABusiness_autoCreatesItsAgent(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        app(VerifyBusinessAction::class)->execute($business->id);

        $agent = app(AgentRepositoryInterface::class)->findByBusinessId($business->id);

        $this->assertNotNull($agent);
        $this->assertSame('Test Company', $agent->nameEn());
        $this->assertNotNull($agent->coreAgentId());
        $this->assertDatabaseHas('agents', ['id' => $agent->coreAgentId(), 'tenant_id' => $business->tenantId]);
    }
}
