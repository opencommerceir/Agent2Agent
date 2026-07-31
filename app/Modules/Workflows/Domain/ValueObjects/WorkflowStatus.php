<?php

namespace App\Modules\Workflows\Domain\ValueObjects;

/**
 * WorkflowEvaluator only ever fires a workflow whose status is Active
 * (Workflow::isActive()) — Inactive/Paused both suppress triggering, the
 * distinction between the two is purely informational this stage (no
 * Action treats them differently yet).
 */
enum WorkflowStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Paused = 'paused';
}
