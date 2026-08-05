<?php

namespace App\Modules\AgentOrchestrator\Domain\Services;

use App\Core\Application\DTOs\AuthContext;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;

/**
 * Runs every ExecutionStep of an ExecutionPlan, in order, via
 * ToolInvokerInterface. `PlanExecutor` (Application/Services) is the one
 * implementation — see ToolInvokerInterface's own docblock for why this
 * Interface takes AuthContext directly (the one documented exception to
 * HANDOFF §3 pattern #1 in this module).
 *
 * A failed step must never abort the whole plan (this module's own rule
 * §و.7 / the parent request's explicit "continue on failure" requirement)
 * — every implementation of this Interface must uphold that, the same
 * way `ProcessBulkImportJob`'s own per-row try/catch does (HANDOFF §7.23).
 */
interface PlanExecutorInterface
{
    public function execute(ExecutionPlan $plan, AuthContext $context): ExecutionResult;
}
