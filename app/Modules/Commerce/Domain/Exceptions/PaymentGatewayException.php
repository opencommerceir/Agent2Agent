<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a real redirect-based gateway (Zibal, Stripe) itself
 * reports a failure — a network error, a non-2xx response, or an
 * explicit non-success result code. Deliberately implements **neither**
 * Core marker interface (§1/§3.2, same reasoning
 * `WooCommerceApiException`/`ShippingProviderException` already give): an
 * upstream gateway failure is neither "not found" nor "a business-rule
 * conflict" — it falls through `MCPExceptionHandler`'s default branch to
 * `INTERNAL_ERROR` (500), the semantically correct status for a broken
 * external dependency.
 */
final class PaymentGatewayException extends RuntimeException
{
}
