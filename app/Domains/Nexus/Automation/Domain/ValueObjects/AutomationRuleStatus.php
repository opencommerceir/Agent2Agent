<?php

namespace App\Domains\Nexus\Automation\Domain\ValueObjects;

enum AutomationRuleStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
}
