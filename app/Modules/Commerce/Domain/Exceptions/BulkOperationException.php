<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use RuntimeException;

/**
 * A generic, unrecoverable failure of a whole BulkOperation (e.g. a Job
 * crashing before it can record any per-row outcome at all) — deliberately
 * implements neither Core marker interface, the same reasoning
 * `WooCommerceApiException`/`ShippingProviderException` already give: this
 * is neither "not found" nor "a business-rule conflict," so it falls
 * through `MCPExceptionHandler`'s default branch to `INTERNAL_ERROR` (500).
 */
final class BulkOperationException extends RuntimeException
{
}
