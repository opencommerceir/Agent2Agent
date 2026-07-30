<?php

namespace App\Core\Domain\ValueObjects;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
