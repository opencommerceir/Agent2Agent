<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * A narrower sibling of InvalidCouponException — specifically
 * "this coupon has expired" — kept separate so a caller can distinguish
 * an expired coupon from every other reason a coupon might be rejected
 * (same OrderAlreadyCancelledException-vs-InvalidOrderStatusException
 * split Order Management already established).
 */
final class CouponExpiredException extends RuntimeException implements ConflictExceptionInterface
{
}
