<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class OrderNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
