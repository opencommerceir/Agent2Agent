<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\DiscountRuleData;
use App\Modules\Commerce\Domain\Exceptions\DiscountRuleNotFoundException;
use App\Modules\Commerce\Domain\Repositories\DiscountRuleRepositoryInterface;

final class GetDiscountRuleAction
{
    public function __construct(
        private readonly DiscountRuleRepositoryInterface $rules,
    ) {
    }

    public function execute(int $id, int $tenantId): DiscountRuleData
    {
        $rule = $this->rules->findById($id, $tenantId);

        if (! $rule) {
            throw new DiscountRuleNotFoundException("DiscountRule [{$id}] does not exist.");
        }

        return DiscountRuleData::fromEntity($rule);
    }
}
