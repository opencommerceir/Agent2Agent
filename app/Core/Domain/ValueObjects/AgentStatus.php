<?php

namespace App\Core\Domain\ValueObjects;

enum AgentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
