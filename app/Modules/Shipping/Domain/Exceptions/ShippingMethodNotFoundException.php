<?php

namespace App\Modules\Shipping\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class ShippingMethodNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
