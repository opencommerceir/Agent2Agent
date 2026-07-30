<?php

namespace App\Core\Domain\ValueObjects;

enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Pending = 'pending';
}
