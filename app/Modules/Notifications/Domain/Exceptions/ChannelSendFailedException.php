<?php

namespace App\Modules\Notifications\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown by a ChannelSenderInterface implementation on delivery failure.
 * Deliberately implements neither Core marker interface (same reasoning
 * WooCommerceApiException/ShippingProviderException already give) — but
 * unlike those two, this exception is never allowed to reach
 * MCPExceptionHandler at all: SendNotificationAction catches it
 * internally (after exhausting retries) and dispatches NotificationFailed
 * instead, per this stage's own rule that a channel failure is
 * business-normal, not a system error.
 */
final class ChannelSendFailedException extends RuntimeException
{
}
