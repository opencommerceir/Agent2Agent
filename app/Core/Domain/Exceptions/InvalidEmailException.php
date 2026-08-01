<?php

namespace App\Core\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by Core's own Email VO. Maps to VALIDATION_ERROR (422) via
 * MCPExceptionHandler's existing `InvalidArgumentException` catch-all —
 * no new marker interface needed, same reasoning every other malformed-input
 * VO exception in this codebase already relies on.
 */
final class InvalidEmailException extends InvalidArgumentException
{
}
