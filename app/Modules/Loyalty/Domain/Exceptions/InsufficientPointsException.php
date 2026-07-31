<?php

namespace App\Modules\Loyalty\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * Thrown by LoyaltyAccount::redeem() when the requested amount exceeds
 * the account's current_balance — a legitimate business-rule rejection,
 * not a malformed request or a missing resource (same shape Commerce's
 * InsufficientInventoryException has for an out-of-stock reservation),
 * so it maps to CONFLICT/409 via the Core marker interface rather than
 * VALIDATION_ERROR/422.
 */
final class InsufficientPointsException extends RuntimeException implements ConflictExceptionInterface
{
}
