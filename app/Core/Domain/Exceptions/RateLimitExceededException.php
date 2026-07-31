<?php

namespace App\Core\Domain\Exceptions;

use RuntimeException;

/**
 * Deliberately implements neither Core marker interface (§1/§3.2's
 * NotFoundExceptionInterface/ConflictExceptionInterface) — same reasoning
 * Commerce's WooCommerceApiException docblock gives for its own case: a
 * rate limit is neither "not found" nor "a business-rule conflict," it's
 * its own new status, so MCPExceptionHandler gets a dedicated match arm
 * for it instead of overloading an existing one.
 */
final class RateLimitExceededException extends RuntimeException
{
}
