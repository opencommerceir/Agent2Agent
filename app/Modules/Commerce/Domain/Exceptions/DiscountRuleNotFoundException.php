<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class DiscountRuleNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
