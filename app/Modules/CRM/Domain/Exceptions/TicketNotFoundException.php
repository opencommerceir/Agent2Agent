<?php

namespace App\Modules\CRM\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

final class TicketNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
