<?php

namespace App\Modules\AgentOrchestrator\Domain\ValueObjects;

/**
 * A `DelegationRequest`'s own lifecycle (Phase 6, Stage 5, §7.30). Every
 * case is reachable this stage — unlike several other enums in this
 * codebase, there is no "modeled but not yet reachable" gap here:
 * `Pending` -> `InProgress` -> exactly one of `Completed`/`Failed`/`Timeout`,
 * all driven by `AgentCommunicationService::requestDelegation()` within a
 * single synchronous call.
 */
enum DelegationStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Timeout = 'timeout';
}
