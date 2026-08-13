<?php

namespace App\Domains\Nexus\Audit\Domain\ValueObjects;

enum AuditOutcome: string
{
    case Success = 'success';
    case Denied = 'denied';
    case Error = 'error';
}
