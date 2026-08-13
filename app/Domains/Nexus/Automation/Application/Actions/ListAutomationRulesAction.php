<?php

namespace App\Domains\Nexus\Automation\Application\Actions;

use App\Domains\Nexus\Automation\Application\DTOs\AutomationRuleData;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRuleRepositoryInterface;

final class ListAutomationRulesAction
{
    public function __construct(
        private readonly AutomationRuleRepositoryInterface $rules,
    ) {
    }

    /**
     * @return list<AutomationRuleData>
     */
    public function execute(int $businessId): array
    {
        return array_map(
            fn ($rule) => AutomationRuleData::fromEntity($rule),
            $this->rules->findByBusinessId($businessId),
        );
    }
}
