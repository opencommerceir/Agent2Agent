<?php

namespace App\Domains\Nexus\Contract\Domain\ValueObjects;

enum DisputeCaseStatus: string
{
    case Open = 'open';
    case Mediation = 'mediation';
    case Resolved = 'resolved';
}
