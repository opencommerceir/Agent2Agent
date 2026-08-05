<?php

namespace App\Modules\AgentOrchestrator\Domain\ValueObjects;

/**
 * Informational metadata a Planner attaches to a step to describe its own
 * relative importance — it does not currently affect execution order or
 * concurrency. PlanExecutor always runs an ExecutionPlan's steps strictly
 * in the order the Planner returned them (sequential, one at a time). A
 * future Planner (or a smarter PlanExecutor) reordering/parallelizing by
 * Priority is real, unbuilt future work, not a hidden bug — see
 * PlanExecutor's own docblock.
 */
enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
