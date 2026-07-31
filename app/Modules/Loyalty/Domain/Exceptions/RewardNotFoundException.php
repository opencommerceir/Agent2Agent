<?php

namespace App\Modules\Loyalty\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class RewardNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
