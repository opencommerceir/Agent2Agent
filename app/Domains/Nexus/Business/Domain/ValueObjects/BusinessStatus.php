<?php

namespace App\Domains\Nexus\Business\Domain\ValueObjects;

enum BusinessStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
