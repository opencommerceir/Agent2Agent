<?php

namespace App\Domains\Nexus\Negotiation\Domain\ValueObjects;

enum NegotiationMessageType: string
{
    case Proposal = 'proposal';
    case Counter = 'counter';
    case Accept = 'accept';
    case Reject = 'reject';
}
