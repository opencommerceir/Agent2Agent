<?php

namespace App\Modules\AgentOrchestrator\Domain\ValueObjects;

/**
 * `Skipped` is modeled but unreached by anything in this stage — the same
 * "modeled but not all reachable yet" shape `TransferStatus::InTransit`
 * (§7.22) and `RewardType::FreeProduct` (§7.10) already carry. The
 * natural future use is a Planner that can mark a step conditionally
 * unnecessary (e.g. "skip the coupon step if inventory is already
 * healthy") without PlanExecutor ever attempting to invoke it.
 */
enum StepStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
