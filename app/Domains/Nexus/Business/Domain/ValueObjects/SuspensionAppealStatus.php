<?php

namespace App\Domains\Nexus\Business\Domain\ValueObjects;

enum SuspensionAppealStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
}
