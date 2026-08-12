<?php

namespace App\Domains\Nexus\Growth\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by Coalition's own state-machine guard — same shape
 * InvalidNegotiationStateException already established: extends
 * InvalidArgumentException directly, no marker interface.
 */
final class InvalidCoalitionStateException extends InvalidArgumentException
{
}
