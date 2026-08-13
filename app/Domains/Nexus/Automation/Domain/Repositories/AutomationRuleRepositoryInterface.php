<?php

namespace App\Domains\Nexus\Automation\Domain\Repositories;

use App\Domains\Nexus\Automation\Domain\Entities\AutomationRule;

interface AutomationRuleRepositoryInterface
{
    public function save(AutomationRule $rule): AutomationRule;

    public function findById(int $id): ?AutomationRule;

    /**
     * @return list<AutomationRule>
     */
    public function findByBusinessId(int $businessId): array;

    /**
     * @return list<AutomationRule>
     */
    public function findActive(): array;

    public function delete(int $id): void;
}
