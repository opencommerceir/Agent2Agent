<?php

namespace App\Modules\Shipping\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown for a negative Weight. Extends InvalidArgumentException
 * directly (same choice Commerce's InvalidQuantityException/Loyalty's
 * InvalidPointsException make) so MCPExceptionHandler's existing
 * InvalidArgumentException catch-all maps it to 422 VALIDATION_ERROR
 * with no further wiring needed.
 */
final class InvalidWeightException extends InvalidArgumentException
{
}
