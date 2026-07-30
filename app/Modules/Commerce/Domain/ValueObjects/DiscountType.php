<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
}
