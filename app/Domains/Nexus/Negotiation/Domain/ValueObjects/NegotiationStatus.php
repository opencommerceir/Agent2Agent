<?php

namespace App\Domains\Nexus\Negotiation\Domain\ValueObjects;

enum NegotiationStatus: string
{
    case Proposed = 'proposed';
    case Countered = 'countered';
    case PendingApproval = 'pending_approval';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
