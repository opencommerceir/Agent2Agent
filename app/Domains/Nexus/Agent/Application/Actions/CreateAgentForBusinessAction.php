<?php

namespace App\Domains\Nexus\Agent\Application\Actions;

use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\AgentType;
use App\Domains\Nexus\Agent\Application\DTOs\AgentData;
use App\Domains\Nexus\Agent\Domain\Entities\Agent;
use App\Domains\Nexus\Agent\Domain\Events\AgentWasCreatedForBusiness;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use Illuminate\Support\Facades\Event;

/**
 * Creates the Nexus Agent row AND provisions a real Core Agent + AgentToken
 * (RegisterAgentAction/GenerateAgentTokenAction, both pre-existing) so the
 * Agent has genuine MCP-Gateway credentials from day one — Extend, Don't
 * Rebuild, rather than a second auth mechanism. AgentType::Custom is used
 * for the Core identity: none of Core's other cases (Shopping/Analytics/
 * CustomerService) describe a Business-to-Business negotiating agent.
 *
 * Takes $tenantId/$organizationId as plain arguments rather than looking
 * the owning Business up itself — this Action has no dependency on the
 * Business domain's repository (Inter-Module Communication, docs/modules.md);
 * its caller (typically CreateAgentOnBusinessVerifiedListener, reacting to
 * BusinessWasVerified) already has them from the event payload.
 */
final class CreateAgentForBusinessAction
{
    public function __construct(
        private readonly RegisterAgentAction $registerCoreAgent,
        private readonly GenerateAgentTokenAction $generateCoreAgentToken,
        private readonly AgentRepositoryInterface $agents,
    ) {
    }

    public function execute(
        int $businessId,
        int $tenantId,
        int $organizationId,
        string $nameFa,
        string $nameEn,
        ?string $personality = null,
        ?string $tone = null,
        ?array $authorityLimits = null,
        ?array $strategies = null,
    ): AgentData {
        $coreAgent = $this->registerCoreAgent->execute($tenantId, $organizationId, $nameEn, AgentType::Custom->value);
        $coreAgentToken = $this->generateCoreAgentToken->execute($coreAgent->id, "Nexus Agent for Business #{$businessId}");

        $agent = Agent::create(
            businessId: $businessId,
            nameFa: $nameFa,
            nameEn: $nameEn,
            coreAgentId: $coreAgent->id,
            personality: $personality,
            tone: $tone,
            authorityLimits: $authorityLimits,
            strategies: $strategies,
        );
        $agent = $this->agents->save($agent);

        Event::dispatch(new AgentWasCreatedForBusiness($agent));

        return AgentData::fromEntity($agent, $coreAgentToken->plainToken);
    }
}
