<?php

namespace App\Modules\Notifications\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Thrown when a Notification is looked up (by id) and either doesn't
 * exist or belongs to a different tenant — findById() never leaks a
 * cross-tenant row, same "cross-tenant id -> 404, not 403" shape every
 * other module's findById()-based lookup already established.
 */
final class NotificationNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
