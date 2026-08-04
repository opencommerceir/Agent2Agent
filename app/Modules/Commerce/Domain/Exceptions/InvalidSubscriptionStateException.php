<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by Subscription's own state-machine guard (an illegal transition,
 * e.g. pausing an already-cancelled Subscription) — extends
 * InvalidArgumentException directly, no Core marker interface, the same
 * shape Shipment::changeStatus()'s own illegal-transition failure has
 * (mapped generically to VALIDATION_ERROR/422 by MCPExceptionHandler's
 * existing InvalidArgumentException handling, not a dedicated match arm).
 */
final class InvalidSubscriptionStateException extends InvalidArgumentException
{
}
