<?php

namespace App\Domains\Nexus\Contract\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by DisputeCase's own state-machine guard (an illegal transition)
 * — mirrors InvalidNegotiationStateException exactly: extends
 * InvalidArgumentException directly, no marker interface.
 */
final class InvalidDisputeCaseStateException extends InvalidArgumentException
{
}
