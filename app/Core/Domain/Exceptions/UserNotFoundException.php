<?php

namespace App\Core\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class UserNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
