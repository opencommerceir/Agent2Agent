<?php

namespace App\Domains\Nexus\Agent\Domain\Events;

use App\Domains\Nexus\Agent\Domain\Entities\Agent;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a Business's Agent has been created and provisioned
 * with real Core Agent/AgentToken credentials.
 */
final class AgentWasCreatedForBusiness
{
    public function __construct(
        public readonly Agent $agent,
    ) {
    }
}
