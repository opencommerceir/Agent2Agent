<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class SubscriptionPlanNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
