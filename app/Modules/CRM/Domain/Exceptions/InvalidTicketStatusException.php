<?php

namespace App\Modules\CRM\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * Thrown by Ticket::changeStatus() for any transition that isn't a
 * strictly-forward move (Open -> InProgress -> Resolved -> Closed, no
 * regression, no re-targeting the current status). A legitimate
 * business-state conflict, not a malformed request — implements
 * ConflictExceptionInterface so MCPExceptionHandler maps it to 409
 * without Core ever importing this class (same reasoning Commerce's
 * InvalidOrderStatusException gives).
 */
final class InvalidTicketStatusException extends RuntimeException implements ConflictExceptionInterface
{
}
