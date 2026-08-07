<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Thrown when a `tracking_reference` (a PaymentSession's own id — never
 * a gateway-specific trackId/session id, see PaymentSession's own
 * docblock) doesn't resolve to a real, tenant-owned PaymentSession.
 */
final class PaymentSessionNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
