<?php

namespace App\Domains\Nexus\Automation\Domain\Repositories;

use App\Domains\Nexus\Automation\Domain\Entities\AutomationRunLog;

interface AutomationRunLogRepositoryInterface
{
    public function save(AutomationRunLog $log): AutomationRunLog;

    /**
     * @return list<AutomationRunLog>
     */
    public function findByRuleId(int $ruleId): array;
}
