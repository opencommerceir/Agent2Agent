<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class PaymentNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
