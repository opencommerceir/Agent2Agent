<?php

namespace App\Core\Domain\ValueObjects;

/**
 * Mirrors `AgentStatus`'s own 2/3-state shape — User is Core's other
 * human-facing identity entity alongside Agent, and needs the same
 * "an account can be deactivated without deleting it" lifecycle Agent
 * already has (`AgentStatus::Inactive`). No `Suspended` state: nothing in
 * this stage's request distinguishes "temporarily suspended" from
 * "deactivated" for a human Dashboard operator the way Agent's own
 * `Suspended` state does — Active/Inactive is the whole lifecycle asked
 * for.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
