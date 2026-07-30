<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * Covers every reason a Coupon can't be used right now: unknown code,
 * inactive, max uses reached, or the order subtotal is below
 * min_order_amount. A business-rule rejection, not a malformed request —
 * ConflictExceptionInterface maps it to 409.
 */
final class InvalidCouponException extends RuntimeException implements ConflictExceptionInterface
{
}
