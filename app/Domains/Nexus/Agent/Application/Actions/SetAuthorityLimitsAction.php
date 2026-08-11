<?php

namespace App\Domains\Nexus\Agent\Application\Actions;

use App\Domains\Nexus\Agent\Application\DTOs\AgentData;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use InvalidArgumentException;

/**
 * "Agentها نمی‌توانند بدون تأیید انسان اقدامات پرارزش انجام دهند" —
 * authority_limits (e.g. max_deal_value, max_discount_percent) is the
 * data this Agent's future negotiation logic (Phase 2) checks against
 * before ever executing a high-value action without human approval.
 */
final class SetAuthorityLimitsAction
{
    public function __construct(
        private readonly AgentRepositoryInterface $agents,
    ) {
    }

    public function execute(int $agentId, array $authorityLimits): AgentData
    {
        $agent = $this->agents->findById($agentId);

        if (! $agent) {
            throw new InvalidArgumentException("Agent [{$agentId}] does not exist.");
        }

        $agent->setAuthorityLimits($authorityLimits);
        $agent = $this->agents->save($agent);

        return AgentData::fromEntity($agent);
    }
}
