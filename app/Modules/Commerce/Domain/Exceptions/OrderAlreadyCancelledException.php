<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * A narrower sibling of InvalidOrderStatusException: specifically "this
 * order is already Cancelled", kept separate so a caller that wants to
 * treat a repeated cancel as a harmless no-op can catch this one
 * exception type without also swallowing every other invalid-transition
 * case.
 */
final class OrderAlreadyCancelledException extends RuntimeException implements ConflictExceptionInterface
{
}
