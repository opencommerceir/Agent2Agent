<?php

namespace App\Domains\Nexus\Holding\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by HoldingSubsidiary's own state-machine guard — same shape
 * InvalidCoalitionStateException/InvalidNegotiationStateException already
 * established: extends InvalidArgumentException directly, no marker
 * interface.
 */
final class InvalidSubsidiaryStateException extends InvalidArgumentException
{
}
