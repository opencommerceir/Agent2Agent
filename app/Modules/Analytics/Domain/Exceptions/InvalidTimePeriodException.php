<?php

namespace App\Modules\Analytics\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Maps to VALIDATION_ERROR (422) via MCPExceptionHandler's existing
 * `InvalidArgumentException` catch-all — no marker interface needed, same
 * reasoning every malformed-input exception in this codebase relies on.
 */
final class InvalidTimePeriodException extends InvalidArgumentException
{
}
