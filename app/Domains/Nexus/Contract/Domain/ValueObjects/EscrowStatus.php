<?php

namespace App\Domains\Nexus\Contract\Domain\ValueObjects;

enum EscrowStatus: string
{
    case Held = 'held';
    case Released = 'released';
    case Disputed = 'disputed';
    case Refunded = 'refunded';
}
