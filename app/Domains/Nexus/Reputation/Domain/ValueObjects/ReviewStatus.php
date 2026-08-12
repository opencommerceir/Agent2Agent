<?php

namespace App\Domains\Nexus\Reputation\Domain\ValueObjects;

enum ReviewStatus: string
{
    case Published = 'published';
    case Flagged = 'flagged';
    case Removed = 'removed';
}
