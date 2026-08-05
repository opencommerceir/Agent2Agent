<?php

namespace App\Modules\AgentOrchestrator\Domain\Exceptions;

use RuntimeException;

/**
 * A genuinely fatal orchestration-level failure — the Planner itself
 * threw while building a plan, or persistence of the finished
 * ExecutionResult failed. Deliberately NOT what an individual step's own
 * failure produces (that's recorded on the ExecutionStep itself via
 * markAsFailed() and execution continues — this module's own explicit
 * "don't stop on a single tool failure" rule). Implements neither Core
 * marker interface, same reasoning `WooCommerceApiException`/
 * `BulkOperationException` already give (HANDOFF §7.6/§7.23): this is
 * neither "not found" nor "a business-rule conflict," so it falls
 * through MCPExceptionHandler's default branch to INTERNAL_ERROR (500).
 */
final class GoalExecutionFailedException extends RuntimeException
{
}
