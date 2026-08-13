<?php

namespace App\Domains\Nexus\Holding\Domain\ValueObjects;

/**
 * `Dissolved` is terminal — a dissolved Holding never reactivates (matches
 * Coalition's own terminal-state shape); DissolveHoldingAction is not built
 * in this milestone (no roadmap requirement forces it yet), so today only
 * `Active` is ever actually produced. The case exists so the state machine
 * is honest about its own shape rather than pretending only one state will
 * ever exist.
 */
enum HoldingStatus: string
{
    case Active = 'active';
    case Dissolved = 'dissolved';
}
