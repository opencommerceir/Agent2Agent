<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\DiscountRuleData;
use App\Modules\Commerce\Domain\Repositories\DiscountRuleRepositoryInterface;

final class ListDiscountRulesAction
{
    public function __construct(
        private readonly DiscountRuleRepositoryInterface $rules,
    ) {
    }

    /**
     * @return list<DiscountRuleData>
     */
    public function execute(int $tenantId, ?bool $isActive = null): array
    {
        return array_map(
            fn ($rule) => DiscountRuleData::fromEntity($rule),
            $this->rules->listByTenant($tenantId, $isActive),
        );
    }
}
