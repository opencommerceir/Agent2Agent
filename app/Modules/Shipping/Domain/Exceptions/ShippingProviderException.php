<?php

namespace App\Modules\Shipping\Domain\Exceptions;

use RuntimeException;

/**
 * Deliberately implements **neither** Core marker interface — identical
 * reasoning Commerce's `WooCommerceApiException` docblock gives for its
 * own case (§7.6): an upstream provider failure is neither "not found"
 * nor "a business-rule conflict," so it falls through
 * `MCPExceptionHandler`'s default branch to `INTERNAL_ERROR` (500), the
 * semantically correct status for a broken external dependency.
 */
final class ShippingProviderException extends RuntimeException
{
}
