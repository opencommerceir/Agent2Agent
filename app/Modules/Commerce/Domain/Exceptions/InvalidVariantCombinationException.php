<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Extends InvalidArgumentException (not a Core marker interface) so
 * MCPExceptionHandler's existing generic InvalidArgumentException match
 * arm maps it to VALIDATION_ERROR/422 automatically — the same
 * "bad-input-shaped" treatment every other malformed-request exception
 * in this codebase already gets, without needing a dedicated match arm.
 * Thrown when GenerateVariantCombinationsAction is asked to combine an
 * empty attribute list, or an attribute id that doesn't exist/belong to
 * this tenant.
 */
final class InvalidVariantCombinationException extends InvalidArgumentException
{
}
