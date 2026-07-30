<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

enum CartStatus: string
{
    case Active = 'active';
    case CheckedOut = 'checked_out';
    case Abandoned = 'abandoned';
}
