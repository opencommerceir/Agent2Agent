<?php

namespace App\Domains\Nexus\Approval\Domain\ValueObjects;

enum ApprovalDecisionOutcome: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
