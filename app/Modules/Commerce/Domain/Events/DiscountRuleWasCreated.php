<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\DiscountRule;

final class DiscountRuleWasCreated
{
    public function __construct(
        public readonly DiscountRule $rule,
    ) {
    }
}
