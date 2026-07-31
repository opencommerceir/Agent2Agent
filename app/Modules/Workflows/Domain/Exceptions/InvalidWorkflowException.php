<?php

namespace App\Modules\Workflows\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown for a malformed Workflow definition (no rules, no actions, or
 * an action naming an `actionType` ExecuteWorkflowActionAction doesn't
 * recognize) — a malformed request, not a missing resource or a
 * business-state conflict. Extends InvalidArgumentException directly
 * (same choice Commerce's InvalidSKUException/InvalidEmailException
 * make) rather than implementing a Core marker interface, so
 * MCPExceptionHandler's existing `InvalidArgumentException` catch-all
 * maps it to 422 VALIDATION_ERROR with no further wiring needed.
 */
final class InvalidWorkflowException extends InvalidArgumentException
{
}
