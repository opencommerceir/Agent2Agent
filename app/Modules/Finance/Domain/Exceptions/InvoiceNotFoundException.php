<?php

namespace App\Modules\Finance\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class InvoiceNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
