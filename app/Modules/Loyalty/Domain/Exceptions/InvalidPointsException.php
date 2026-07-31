<?php

namespace App\Modules\Loyalty\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown for a malformed points value: a negative Points VO, a
 * PointTransaction whose sign doesn't match its TransactionType (earn/
 * bonus must be positive, redeem/expire must be negative — see
 * PointTransaction::record()'s docblock), or a `loyalty.points.redeem`
 * call whose `points` input doesn't match the named Reward's
 * `points_required`. A malformed request, not a missing resource or a
 * business-state conflict — extends InvalidArgumentException directly
 * (same choice Commerce's InvalidQuantityException/Workflows'
 * InvalidWorkflowException make) so MCPExceptionHandler's existing
 * InvalidArgumentException catch-all maps it to 422 VALIDATION_ERROR
 * with no further wiring needed.
 */
final class InvalidPointsException extends InvalidArgumentException
{
}
