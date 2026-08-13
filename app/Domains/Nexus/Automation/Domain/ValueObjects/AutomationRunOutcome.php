<?php

namespace App\Domains\Nexus\Automation\Domain\ValueObjects;

enum AutomationRunOutcome: string
{
    case Triggered = 'triggered';
    case Failed = 'failed';
}
