<?php

namespace App\Domains\Nexus\Negotiation\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by Negotiation's own state-machine guard (an illegal transition,
 * e.g. accepting an already-rejected Negotiation) — mirrors Commerce's
 * InvalidSubscriptionStateException exactly: extends
 * InvalidArgumentException directly, no marker interface.
 */
final class InvalidNegotiationStateException extends InvalidArgumentException
{
}
