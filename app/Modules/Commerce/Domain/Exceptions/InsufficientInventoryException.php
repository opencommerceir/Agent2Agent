<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

final class InsufficientInventoryException extends RuntimeException implements ConflictExceptionInterface
{
}
