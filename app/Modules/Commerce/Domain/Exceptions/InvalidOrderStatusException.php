<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * Thrown for any status transition the Order entity's own state machine
 * rejects (e.g. updating a Delivered order, or targeting Cancelled/
 * Refunded through the generic UpdateOrderStatusAction instead of the
 * dedicated CancelOrderAction). A legitimate business-state conflict, not
 * a malformed request — implements ConflictExceptionInterface so
 * MCPExceptionHandler maps it to 409 without Core ever importing this
 * class.
 */
final class InvalidOrderStatusException extends RuntimeException implements ConflictExceptionInterface
{
}
