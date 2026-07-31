<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a call to the WooCommerce REST API fails (network error,
 * non-2xx response, malformed payload) — an upstream/infrastructure
 * failure, not a business-rule rejection or a missing platform resource.
 * Deliberately does NOT implement NotFoundExceptionInterface or
 * ConflictExceptionInterface: neither 404 nor 409 fits "the external
 * system is unreachable or broke its contract", so it falls through to
 * MCPExceptionHandler's default INTERNAL_ERROR (500) — the semantically
 * correct status for an upstream dependency failure.
 */
final class WooCommerceApiException extends RuntimeException
{
}
