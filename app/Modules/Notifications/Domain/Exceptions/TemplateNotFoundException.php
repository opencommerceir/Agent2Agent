<?php

namespace App\Modules\Notifications\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class TemplateNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
