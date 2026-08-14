<?php

namespace App\Domains\Nexus\Agent\Application\Actions;

use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use InvalidArgumentException;

/**
 * The business-facing toggle for the reactive Autonomous Agent Runtime
 * (AutoRespondToNegotiationListener) — opt-in, off by default (see
 * Agent::autoRespondEnabled()'s own docblock for why). Merges into the
 * existing free-form `strategies` bag rather than overwriting it, so this
 * never clobbers a `tolerance_percent` (or any future key) set elsewhere.
 */
final class SetAutoRespondAction
{
    public function __construct(
        private readonly AgentRepositoryInterface $agents,
    ) {
    }

    public function execute(int $businessId, bool $enabled): void
    {
        $agent = $this->agents->findByBusinessId($businessId);

        if (! $agent) {
            throw new InvalidArgumentException("No Agent exists yet for Business [{$businessId}].");
        }

        $agent->setStrategies([...($agent->strategies() ?? []), 'auto_respond' => $enabled]);
        $this->agents->save($agent);
    }
}
