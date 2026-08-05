<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\Exceptions\AgentProfileNotFoundException;
use App\Modules\AgentOrchestrator\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use Tests\TestCase;

/**
 * A Feature test, not a framework-free Unit test, because this repository
 * reads real `config/agents/*.php` files through Laravel's own `config()`
 * repository — exactly the thing under test (see this class's own
 * docblock for why `config()`, not `glob()`, is what makes it work
 * correctly under `config:cache`).
 */
class ConfigBasedAgentProfileRepositoryTest extends TestCase
{
    public function test_findByType_loadsTheRealCeoConfigFile(): void
    {
        $repository = app(AgentProfileRepositoryInterface::class);

        $profile = $repository->findByType('ceo');

        $this->assertSame(AgentType::Ceo, $profile->type);
        $this->assertSame('CEO Agent', $profile->name);
        $this->assertNotEmpty($profile->getCapabilitiesForGoal('increase sales'));
    }

    public function test_findByType_throwsForAnUnknownType(): void
    {
        $repository = app(AgentProfileRepositoryInterface::class);

        $this->expectException(AgentProfileNotFoundException::class);

        $repository->findByType('marketing');
    }

    public function test_listAll_returnsEveryConfiguredProfileIncludingCeoAndSales(): void
    {
        $repository = app(AgentProfileRepositoryInterface::class);

        $types = array_map(fn ($profile) => $profile->type->value, $repository->listAll());

        $this->assertContains('ceo', $types);
        $this->assertContains('sales', $types);
        $this->assertContains('support', $types);
        $this->assertContains('finance', $types);
    }
}
