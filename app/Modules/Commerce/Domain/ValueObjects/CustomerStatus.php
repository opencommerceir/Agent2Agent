<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

enum CustomerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blacklisted = 'blacklisted';
}
