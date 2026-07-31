<?php

namespace App\Modules\Workflows\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class WorkflowNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
