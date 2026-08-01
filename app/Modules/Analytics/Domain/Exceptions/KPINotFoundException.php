<?php

namespace App\Modules\Analytics\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class KPINotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
