<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use InvalidArgumentException;

/**
 * A whole-file problem (missing file, unreadable, missing required
 * header columns) — not a single row's own validation failure, which
 * `ValidationResult` handles instead without ever throwing. Extends
 * `InvalidArgumentException` directly, no marker interface, the same
 * shape `InvalidSKUException` already has — `MCPExceptionHandler` maps a
 * plain `InvalidArgumentException` to `VALIDATION_ERROR` (422).
 */
final class InvalidCsvFormatException extends InvalidArgumentException
{
}
