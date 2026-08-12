<?php

namespace App\Domains\Nexus\Credit\Domain\ValueObjects;

enum CreditPurchaseSessionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
