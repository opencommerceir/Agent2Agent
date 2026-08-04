<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Extends InvalidArgumentException directly, no marker interface, the
 * same shape InvalidSKUException/InvalidCsvFormatException already have —
 * maps to VALIDATION_ERROR (422) via MCPExceptionHandler's existing
 * handling of a plain InvalidArgumentException.
 */
final class InvalidDiscountRuleException extends InvalidArgumentException
{
}
