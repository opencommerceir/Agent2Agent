<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * A legitimate business outcome (the Payment Gateway declined the
 * charge), not a malformed request or a missing resource —
 * ConflictExceptionInterface maps it to 409, same reasoning as
 * InsufficientInventoryException.
 */
final class PaymentFailedException extends RuntimeException implements ConflictExceptionInterface
{
}
