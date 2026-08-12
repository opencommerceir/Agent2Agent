<?php

namespace App\Domains\Nexus\Business\Domain\ValueObjects;

enum SuspensionAction: string
{
    case Suspended = 'suspended';
    case Reactivated = 'reactivated';
}
