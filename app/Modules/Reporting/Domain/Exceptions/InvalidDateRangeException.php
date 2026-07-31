<?php

namespace App\Modules\Reporting\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown for a malformed date range: an unparseable start_date/end_date
 * string, or an end_date before its start_date. A malformed request, not
 * a missing resource or a business-state conflict — extends
 * InvalidArgumentException directly (same choice Workflows'
 * InvalidWorkflowException/Commerce's InvalidQuantityException make) so
 * MCPExceptionHandler's existing InvalidArgumentException catch-all maps
 * it to 422 VALIDATION_ERROR with no further wiring needed.
 */
final class InvalidDateRangeException extends InvalidArgumentException
{
}
