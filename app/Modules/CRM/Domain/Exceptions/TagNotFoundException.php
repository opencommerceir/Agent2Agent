<?php

namespace App\Modules\CRM\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Not part of the original request's exception list, added for the same
 * reason Commerce's Stage 5 added `DiscountRepositoryInterface` even
 * though it wasn't explicitly asked for: AssignTagToCustomerAction needs
 * to reject an unknown tag id with a real 404 rather than letting a
 * dangling foreign key fail loudly and unexplained deep in the
 * Repository (HANDOFF gotcha #8 territory) — every other "does this
 * referenced thing exist" check in this codebase gets its own explicit
 * exception, so Tag shouldn't be the one silent exception.
 */
final class TagNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
