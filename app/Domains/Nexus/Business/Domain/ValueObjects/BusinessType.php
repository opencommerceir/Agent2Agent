<?php

namespace App\Domains\Nexus\Business\Domain\ValueObjects;

enum BusinessType: string
{
    case Company = 'company';
    case Individual = 'individual';
}
