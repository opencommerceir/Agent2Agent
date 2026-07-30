<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\Agent;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after an Agent has been persisted for the first time.
 */
final class AgentWasRegistered
{
    public function __construct(
        public readonly Agent $agent,
    ) {
    }
}
