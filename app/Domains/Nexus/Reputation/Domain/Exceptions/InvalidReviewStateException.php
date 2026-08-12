<?php

namespace App\Domains\Nexus\Reputation\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by Review's own state-machine guard (an illegal moderation
 * transition) — mirrors InvalidNegotiationStateException exactly: extends
 * InvalidArgumentException directly, no marker interface.
 */
final class InvalidReviewStateException extends InvalidArgumentException
{
}
