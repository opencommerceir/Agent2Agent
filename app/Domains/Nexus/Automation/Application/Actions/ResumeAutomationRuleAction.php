<?php

namespace App\Domains\Nexus\Automation\Application\Actions;

use App\Domains\Nexus\Automation\Application\DTOs\AutomationRuleData;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRuleRepositoryInterface;
use InvalidArgumentException;

final class ResumeAutomationRuleAction
{
    public function __construct(
        private readonly AutomationRuleRepositoryInterface $rules,
    ) {
    }

    public function execute(int $ruleId, int $actingBusinessId): AutomationRuleData
    {
        $rule = $this->rules->findById($ruleId);

        if (! $rule || $rule->businessId() !== $actingBusinessId) {
            throw new InvalidArgumentException("AutomationRule [{$ruleId}] does not belong to Business [{$actingBusinessId}].");
        }

        $rule->resume();
        $rule = $this->rules->save($rule);

        return AutomationRuleData::fromEntity($rule);
    }
}
